<?php
declare(strict_types=1);

namespace app\common\command;

use app\model\UserGoldWallet;
use app\model\GoldKline;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * 生成黄金资产快照命令
 * 
 * 使用方法：
 * php think gold:snapshot                                    - 生成今日快照
 * php think gold:snapshot --date=2025-10-20                  - 生成指定日期的快照
 * php think gold:snapshot --force                            - 强制重新生成（覆盖已有数据）
 * php think gold:snapshot --chunk=500                        - 自定义批处理大小（默认100）
 * php think gold:snapshot -d 2025-10-20 -f -c 200            - 组合使用（简写形式）
 */
class GenerateGoldSnapshot extends Command
{
    protected function configure()
    {
        $this->setName('gold:snapshot')
            ->setDescription('生成黄金资产快照数据（用于计算昨日收益）')
            ->addOption('date', 'd', \think\console\input\Option::VALUE_OPTIONAL, '快照日期（格式：YYYY-MM-DD），默认今天', '')
            ->addOption('force', 'f', \think\console\input\Option::VALUE_NONE, '强制重新生成（覆盖已有数据）')
            ->addOption('chunk', 'c', \think\console\input\Option::VALUE_OPTIONAL, '每批处理数量，默认100', '100')
            ->setHelp('该命令用于生成黄金资产快照，建议每天23:59执行一次');
    }
    
    protected function execute(Input $input, Output $output)
    {
        $dateParam = $input->getOption('date');
        $force = $input->getOption('force');
        $chunkSize = intval($input->getOption('chunk'));
        
        // 确保chunk大小合理
        if ($chunkSize < 10) {
            $chunkSize = 10;
        } elseif ($chunkSize > 1000) {
            $chunkSize = 1000;
        }
        
        // 确定快照日期
        $snapshotDate = $dateParam ? date('Y-m-d', strtotime($dateParam)) : date('Y-m-d');
        $snapshotTime = date('Y-m-d H:i:s');
        
        $output->writeln('<info>开始生成黄金资产快照...</info>');
        $output->writeln("快照日期: {$snapshotDate}");
        $output->writeln("快照时间: {$snapshotTime}");
        $output->writeln("批处理大小: {$chunkSize}");
        
        try {
            // 获取当前市场价格（使用日K线的最新收盘价）
            $marketPrice = $this->getLatestGoldPrice($output);
            
            if (!$marketPrice) {
                $output->writeln('<error>无法获取黄金市场价格，请先同步K线数据</error>');
                return;
            }
            
            $output->writeln("当前金价: {$marketPrice} 元/克");
            
            // 统计总用户数
            $totalUsers = UserGoldWallet::where('gold_balance', '>', 0)->count();
            $output->writeln("找到 {$totalUsers} 个持有黄金的用户");
            
            if ($totalUsers == 0) {
                $output->writeln('<info>没有用户持有黄金，无需生成快照</info>');
                return;
            }
            
            $successCount = 0;
            $failCount = 0;
            $skipCount = 0;
            
            // 使用chunk方法分批处理，避免内存溢出
            UserGoldWallet::where('gold_balance', '>', 0)
                ->chunk($chunkSize, function ($wallets) use (
                    $snapshotDate, 
                    $snapshotTime, 
                    $marketPrice, 
                    $force, 
                    &$successCount, 
                    &$failCount, 
                    &$skipCount, 
                    $output,
                    $totalUsers
                ) {
                    // 批量准备数据
                    $batchInsertData = [];
                    $updateList = [];
                    
                    foreach ($wallets as $wallet) {
                        try {
                            // 检查是否已存在快照
                            $exists = Db::name('gold_asset_snapshot')
                                        ->where('user_id', $wallet->user_id)
                                        ->where('snapshot_date', $snapshotDate)
                                        ->find();
                            
                            if ($exists && !$force) {
                                $skipCount++;
                                continue;
                            }
                            
                            // 计算各项指标
                            $goldBalance = floatval($wallet->gold_balance);
                            $costPrice = floatval($wallet->cost_price);
                            $totalCost = $goldBalance * $costPrice;
                            $marketValue = $goldBalance * $marketPrice;
                            $unrealizedProfit = $marketValue - $totalCost;
                            $realizedProfit = floatval($wallet->realized_profit);
                            $totalProfit = $unrealizedProfit + $realizedProfit;
                            $profitRate = $totalCost > 0 ? ($totalProfit / $totalCost * 100) : 0;
                            
                            $snapshotData = [
                                'user_id' => $wallet->user_id,
                                'gold_balance' => $goldBalance,
                                'cost_price' => $costPrice,
                                'market_price' => $marketPrice,
                                'market_value' => $marketValue,
                                'total_cost' => $totalCost,
                                'unrealized_profit' => $unrealizedProfit,
                                'realized_profit' => $realizedProfit,
                                'total_profit' => $totalProfit,
                                'total_asset' => $marketValue,
                                'profit_rate' => round($profitRate, 4),
                                'snapshot_date' => $snapshotDate,
                                'snapshot_time' => $snapshotTime,
                                'created_at' => $snapshotTime,
                            ];
                            
                            if ($exists && $force) {
                                // 记录需要更新的数据
                                $updateList[] = [
                                    'id' => $exists['id'],
                                    'data' => $snapshotData
                                ];
                            } else {
                                // 收集批量插入的数据
                                $batchInsertData[] = $snapshotData;
                            }
                            
                        } catch (\Exception $e) {
                            $failCount++;
                            $output->writeln("<error>用户 {$wallet->user_id} 数据准备失败: {$e->getMessage()}</error>");
                            Log::error("黄金资产快照数据准备失败", [
                                'user_id' => $wallet->user_id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }
                    
                    // 批量插入新数据
                    if (!empty($batchInsertData)) {
                        try {
                            Db::name('gold_asset_snapshot')->insertAll($batchInsertData);
                            $successCount += count($batchInsertData);
                        } catch (\Exception $e) {
                            $failCount += count($batchInsertData);
                            $output->writeln("<error>批量插入失败: {$e->getMessage()}</error>");
                            Log::error("黄金资产快照批量插入失败", [
                                'count' => count($batchInsertData),
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    
                    // 批量更新已存在的数据
                    if (!empty($updateList)) {
                        foreach ($updateList as $item) {
                            try {
                                Db::name('gold_asset_snapshot')
                                    ->where('id', $item['id'])
                                    ->update($item['data']);
                                $successCount++;
                            } catch (\Exception $e) {
                                $failCount++;
                                $output->writeln("<error>更新ID {$item['id']} 失败: {$e->getMessage()}</error>");
                            }
                        }
                    }
                    
                    // 输出进度
                    if ($successCount > 0 && $successCount % 100 == 0) {
                        $output->writeln("已处理: {$successCount}/{$totalUsers}");
                    }
                });
            
            $output->writeln('<info>快照生成完成！</info>');
            $output->writeln("成功: {$successCount} 条");
            $output->writeln("失败: {$failCount} 条");
            $output->writeln("跳过: {$skipCount} 条（已存在，使用 --force 可强制覆盖）");
            
            // 记录日志
            Log::info("黄金资产快照生成完成", [
                'snapshot_date' => $snapshotDate,
                'total_users' => $totalUsers,
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'skip_count' => $skipCount,
                'market_price' => $marketPrice
            ]);
            
        } catch (\Exception $e) {
            $output->writeln('<error>执行失败: ' . $e->getMessage() . '</error>');
            Log::error('黄金资产快照生成任务执行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 获取最新黄金价格
     * @param Output $output
     * @return float|null
     */
    private function getLatestGoldPrice(Output $output)
    {
        try {
            // 优先获取日K线的最新收盘价
            $latestKline = GoldKline::where('period', '1day')
                                   ->where('price_type', 'CNY')
                                   ->order('start_time', 'desc')
                                   ->find();
            
            if ($latestKline) {
                return floatval($latestKline->close_price);
            }
            
            // 如果没有日K线，尝试获取其他周期的最新价格
            $latestKline = GoldKline::where('price_type', 'CNY')
                                   ->order('start_time', 'desc')
                                   ->find();
            
            if ($latestKline) {
                $output->writeln("<comment>注意：未找到日K线数据，使用 {$latestKline->period} 周期的价格</comment>");
                return floatval($latestKline->close_price);
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('获取黄金价格失败', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}


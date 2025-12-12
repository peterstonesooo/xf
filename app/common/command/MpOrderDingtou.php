<?php

namespace app\common\command;

use app\model\OrderDingtou;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class MpOrderDingtou extends Command
{
    protected function configure()
    {
        $this->setName('mp_order_dingtou')
            ->setDescription('定投未完成10次的用户返还定投金额');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始执行定投返还任务...');
        
        try {
            $successCount = 0;
            $failCount = 0;
            $totalReturnAmount = 0;
            $processedUsers = []; // 记录已处理的用户，避免重复处理

            // 先获取所有有定投记录的用户ID，排除已经返还过的用户（is_admin_confirm = 2）
            $userIds = OrderDingtou::where('status', 2) // 只查询已支付成功的订单
                ->where('is_admin_confirm', '<>', 2) // 排除已经返还过的用户
                ->group('user_id')
                ->column('user_id');

            $output->writeln('找到 ' . count($userIds) . ' 个有定投记录的用户');

            // 使用 chunk 分批处理，提高效率
            $chunkSize = 100;
            $chunks = array_chunk($userIds, $chunkSize);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $userId) {
                    // 跳过已处理的用户
                    if (isset($processedUsers[$userId])) {
                        continue;
                    }

                    Db::startTrans();
                    try {
                        // 获取该用户最新的定投记录，排除已经返还过的
                        $latestOrder = OrderDingtou::where('user_id', $userId)
                            ->where('status', 2) // 只查询已支付成功的订单
                            ->where('is_admin_confirm', '<>', 2) // 排除已经返还过的
                            ->order('id', 'desc')
                            ->find();

                        // 如果最新记录的total_num不等于10，则需要返还
                        if ($latestOrder && $latestOrder['total_num'] != 10) {
                            // 计算该用户所有定投记录的总金额（排除已返还的）
                            $totalAmount = OrderDingtou::where('user_id', $userId)
                                ->where('status', 2) // 只查询已支付成功的订单
                                ->where('is_admin_confirm', '<>', 2) // 排除已经返还过的
                                ->sum('price'); // 使用price字段，这是实际支付金额

                            if ($totalAmount > 0) {
                                // 返还金额到用户的充值余额
                                User::changeInc(
                                    $userId,
                                    $totalAmount,
                                    'topup_balance',
                                    63, // type: 定投返还
                                    0, // relation_id
                                    1, // log_type: 余额
                                    '定投未完成10次返还',
                                    0, // admin_user_id
                                    2, // status: 已完成
                                    'DT', // sn_prefix
                                    1 // is_delete
                                );

                                // 将该用户所有定投记录的 is_admin_confirm 设置为 2，标记已返还
                                OrderDingtou::where('user_id', $userId)
                                    ->where('status', 2)
                                    ->update(['is_admin_confirm' => 2]);

                                $totalReturnAmount += $totalAmount;
                                $successCount++;
                                
                                $output->writeln("用户ID: {$userId}, 返还金额: {$totalAmount}, 最新定投次数: {$latestOrder['total_num']}");
                                
                                Log::info('定投返还成功', [
                                    'user_id' => $userId,
                                    'return_amount' => $totalAmount,
                                    'latest_total_num' => $latestOrder['total_num']
                                ]);
                            }
                        }

                        // 标记用户已处理
                        $processedUsers[$userId] = true;

                        Db::commit();
                    } catch (\Exception $e) {
                        Db::rollback();
                        $failCount++;
                        $output->writeln("用户ID: {$userId} 处理失败: " . $e->getMessage());
                        Log::error('定投返还失败', [
                            'user_id' => $userId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            $output->writeln("处理完成！");
            $output->writeln("成功: {$successCount} 个用户");
            $output->writeln("失败: {$failCount} 个用户");
            $output->writeln("总返还金额: {$totalReturnAmount}");

        } catch (\Exception $e) {
            $output->writeln('执行出错：' . $e->getMessage());
            Log::error('定投返还任务执行异常：' . $e->getMessage());
        }
    }
}

<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserSigninRewardLog;
use app\model\GoldOrder;
use app\model\UserGoldWallet;
use app\model\Project;
use Exception;

/**
 * 修复签到黄金奖励错误发放的问题
 * 
 * 问题说明：
 * 之前使用 User::changeInc 直接增加 gold_wallet 字段，没有创建 GoldOrder 和更新 UserGoldWallet 统计信息
 * 
 * 修复逻辑：
 * 1. 查找所有错误的记录（UserBalanceLog: type=100, log_type=18, remark='签到奖励', order_sn LIKE 'SR%'）
 * 2. 为每条记录创建对应的 GoldOrder
 * 3. 更新 UserGoldWallet 的统计信息
 */
class RepairSigninGoldReward extends Command
{
    protected function configure()
    {
        $this->setName('repair:signin-gold-reward')
            ->setDescription('修复签到黄金奖励错误发放的问题')
            ->addOption('dry-run', null, \think\console\input\Option::VALUE_NONE, '仅查看需要修复的记录，不执行修复操作')
            ->addOption('confirm', null, \think\console\input\Option::VALUE_NONE, '确认执行修复操作');
    }

    protected function execute(Input $input, Output $output)
    {
        $isDryRun = $input->getOption('dry-run');
        $isConfirmed = $input->getOption('confirm');

        try {
            if ($isDryRun) {
                $output->writeln('<info>=== 预览模式：仅查看需要修复的记录 ===</info>');
            } else {
                if (!$isConfirmed) {
                    $output->writeln('<error>请使用 --confirm 选项确认执行修复操作</error>');
                    return 1;
                }
                $output->writeln('<info>=== 开始修复签到黄金奖励错误发放的问题 ===</info>');
            }

            // 查找所有错误的记录
            // type=100, log_type=18, remark='签到奖励', order_sn LIKE 'SR%', change_balance=100
            $errorRecords = UserBalanceLog::alias('l')
                ->join('mp_user u', 'l.user_id = u.id')
                ->where('l.type', 100)
                ->where('l.log_type', 18)
                ->where('l.remark', '签到奖励')
                ->where('l.order_sn', 'like', 'SR%')
                ->where('l.change_balance', 100)
                ->field('l.*, u.phone')
                ->order('l.created_at', 'asc')
                ->select();

            if (empty($errorRecords)) {
                $output->writeln('<info>没有找到需要修复的记录</info>');
                return 0;
            }

            $output->writeln('<info>找到 ' . count($errorRecords) . ' 条错误记录</info>');

            // 显示记录详情
            $output->writeln('');
            $output->writeln('<comment>错误记录详情：</comment>');
            $output->writeln(str_repeat('-', 160));
            $output->writeln(sprintf('%-10s %-15s %-20s %-15s %-15s %-20s %-30s',
                       'ID', '用户ID', '手机号', '关联ID', '金额', '订单号', '创建时间'));
            $output->writeln(str_repeat('-', 160));

            foreach ($errorRecords as $record) {
                $output->writeln(sprintf('%-10s %-15s %-20s %-15s %-15s %-20s %-30s',
                           $record['id'],
                           $record['user_id'],
                           $record['phone'],
                           $record['relation_id'],
                           $record['change_balance'],
                           $record['order_sn'],
                           $record['created_at']));
            }
            $output->writeln(str_repeat('-', 160));
            $output->writeln('');

            if ($isDryRun) {
                $output->writeln('<info>预览模式结束，使用 --confirm 选项执行实际修复</info>');
                return 0;
            }

            // 开始修复
            $successCount = 0;
            $skipCount = 0;
            $errorCount = 0;

            foreach ($errorRecords as $record) {
                try {
                    $result = $this->repairRecord($record, $output);
                    if ($result === 'success') {
                        $successCount++;
                    } elseif ($result === 'skipped') {
                        $skipCount++;
                    } else {
                        $errorCount++;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $output->writeln("<error>修复记录 ID {$record['id']} 失败: " . $e->getMessage() . "</error>");
                }
            }

            $output->writeln('');
            $output->writeln('<info>修复完成！</info>');
            $output->writeln("<info>成功: {$successCount} 条</info>");
            $output->writeln("<comment>跳过: {$skipCount} 条（已存在GoldOrder）</comment>");
            $output->writeln("<error>失败: {$errorCount} 条</error>");

        } catch (Exception $e) {
            $output->writeln("<error>任务执行失败: " . $e->getMessage() . "</error>");
            return 1;
        }

        return 0;
    }

    /**
     * 修复单条记录
     * @param array $record UserBalanceLog记录
     * @param Output $output
     * @return string 'success'|'skipped'|'error'
     */
    private function repairRecord($record, $output)
    {
        $userId = $record['user_id'];
        $relationId = $record['relation_id'];
        $balanceLogId = $record['id'];
        $quantity = 100.0; // 黄金克数
        $createdAt = $record['created_at'];

        // 检查是否已存在对应的 GoldOrder
        // 通过 relation_id 查找对应的 UserSigninRewardLog，然后查找是否有对应的 GoldOrder
        $rewardLog = UserSigninRewardLog::where('id', $relationId)->find();
        if (!$rewardLog) {
            $output->writeln("<comment>记录 ID {$balanceLogId}: 关联的 UserSigninRewardLog (ID: {$relationId}) 不存在，跳过</comment>");
            return 'skipped';
        }

        // 检查是否已存在对应的 GoldOrder（通过 remark 和创建时间来判断）
        $existingGoldOrder = GoldOrder::where('user_id', $userId)
            ->where('type', GoldOrder::TYPE_REWARD)
            ->where('quantity', $quantity)
            ->where('remark', '签到奖励')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime($createdAt) - 60)) // 允许1分钟误差
            ->where('created_at', '<=', date('Y-m-d H:i:s', strtotime($createdAt) + 60))
            ->find();

        if ($existingGoldOrder) {
            $output->writeln("<comment>记录 ID {$balanceLogId}: 已存在对应的 GoldOrder (ID: {$existingGoldOrder->id})，跳过</comment>");
            return 'skipped';
        }

        Db::startTrans();
        try {
            // 获取或创建用户黄金钱包
            $wallet = UserGoldWallet::getOrCreate($userId);
            $balanceBefore = round(floatval($wallet['gold_balance']), 6);
            $costPriceBefore = round(floatval($wallet['cost_price']), 6);

            // 获取金价（使用当前金价，如果有历史金价表可以用历史金价）
            $goldPrice = $this->getGoldPriceAtTime($createdAt);
            if ($goldPrice <= 0) {
                throw new Exception("无法获取金价");
            }
            $goldPrice = round($goldPrice, 4);

            // 计算金额和余额
            $amount = round($goldPrice * $quantity, 2);
            $balanceAfter = round($balanceBefore + $quantity, 6);

            // 计算新的成本价（加权平均）
            if ($goldPrice > 0 && $balanceAfter > 0) {
                if ($balanceBefore <= 0) {
                    $costPriceAfter = $goldPrice;
                } else {
                    $costPriceAfter = round((($costPriceBefore * $balanceBefore) + ($goldPrice * $quantity)) / $balanceAfter, 6);
                }
            } else {
                $costPriceAfter = $costPriceBefore;
            }

            // 创建 GoldOrder
            $orderNo = 'GOLDREWARD' . date('YmdHis', strtotime($createdAt)) . str_pad($userId, 6, '0', STR_PAD_LEFT) . rand(1000, 9999);
            $goldOrder = GoldOrder::create([
                'order_no' => $orderNo,
                'user_id' => $userId,
                'type' => GoldOrder::TYPE_REWARD,
                'quantity' => $quantity,
                'price' => $goldPrice,
                'amount' => $amount,
                'fee' => 0,
                'fee_rate' => 0,
                'actual_amount' => $amount,
                'cost_price_before' => $costPriceBefore,
                'cost_price_after' => $costPriceAfter,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'profit' => 0,
                'status' => GoldOrder::STATUS_COMPLETED,
                'remark' => '签到奖励',
                'created_at' => $createdAt, // 使用原始创建时间
            ]);

            // 更新 UserBalanceLog 的 relation_id 为 GoldOrder 的 ID（如果需要的话，这里保持原样）
            // 注意：relation_id 保持为 UserSigninRewardLog 的 ID，这样逻辑更清晰

            // 更新 UserGoldWallet 统计信息
            $wallet->gold_balance = $balanceAfter;
            if ($goldPrice > 0 && $balanceAfter > 0) {
                $wallet->cost_price = $costPriceAfter;
            }
            $wallet->total_buy_quantity = round(floatval($wallet['total_buy_quantity']) + $quantity, 6);
            if ($amount > 0) {
                $wallet->total_buy_amount = round(floatval($wallet['total_buy_amount']) + $amount, 2);
            }
            $wallet->save();

            Db::commit();

            $output->writeln("<info>记录 ID {$balanceLogId}: 修复成功，创建 GoldOrder ID: {$goldOrder->id}</info>");
            return 'success';

        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 获取指定时间的金价
     * @param string $datetime 日期时间字符串
     * @return float
     */
    private function getGoldPriceAtTime($datetime)
    {
        $timestamp = strtotime($datetime);
        $date = date('Y-m-d', $timestamp);

        // 尝试从 GoldKline 表获取当天的金价
        $kline = \app\model\GoldKline::where([
            'period' => '1day',
            'price_type' => 'CNY'
        ])
        ->where('start_datetime', '<=', $datetime)
        ->where('end_datetime', '>=', $datetime)
        ->order('start_time', 'desc')
        ->find();

        if ($kline) {
            return floatval($kline->close_price);
        }

        // 如果找不到当天的，找最近的一条
        $kline = \app\model\GoldKline::where([
            'period' => '1day',
            'price_type' => 'CNY'
        ])
        ->where('start_time', '<=', $timestamp)
        ->order('start_time', 'desc')
        ->find();

        if ($kline) {
            return floatval($kline->close_price);
        }

        // 如果都找不到，查询最新金价作为兜底
        $kline = \app\model\GoldKline::where([
            'period' => '1min',
            'price_type' => 'CNY'
        ])->order('start_time', 'desc')->find();

        if ($kline) {
            return floatval($kline->close_price);
        }

        $kline = \app\model\GoldKline::where([
            'period' => '1day',
            'price_type' => 'CNY'
        ])->order('start_time', 'desc')->find();

        return $kline ? floatval($kline->close_price) : 0;
    }
}


<?php
declare(strict_types=1);

namespace app\common\command;

use app\model\InvestmentRecord;
use app\model\InvestmentReturnRecord;
use app\model\LoanConfig;
use app\model\User;
use app\model\UserBalanceLog;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class InvestmentAutoReturn extends Command
{
    protected function configure()
    {
        $this->setName('investment:auto-return')
             ->setDescription('出资到期自动返还');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始执行出资到期自动返还...');
        
        try {
            // 检查是否启用自动返还
            $autoReturn = LoanConfig::getConfig('investment_auto_return', 1);
            if (!$autoReturn) {
                $output->writeln('自动返还功能已禁用');
                return;
            }

            // 获取已到期但未返还的出资记录
            $expiredInvestments = InvestmentRecord::where('status', 1)
                                                 ->where('end_date', '<=', date('Y-m-d'))
                                                 ->select();

            $output->writeln('找到 ' . count($expiredInvestments) . ' 条到期出资记录');

            $successCount = 0;
            $failCount = 0;

            foreach ($expiredInvestments as $investment) {
                try {
                    $this->processExpiredInvestment($investment);
                    $successCount++;
                    $output->writeln("出资记录 {$investment->id} 自动返还成功");
                } catch (\Exception $e) {
                    $failCount++;
                    $output->writeln("出资记录 {$investment->id} 自动返还失败: " . $e->getMessage());
                    Log::error("出资自动返还失败", [
                        'investment_id' => $investment->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $output->writeln("处理完成: 成功 {$successCount} 条, 失败 {$failCount} 条");

        } catch (\Exception $e) {
            $output->writeln('执行失败: ' . $e->getMessage());
            Log::error('出资自动返还任务执行失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 处理到期的出资记录
     */
    private function processExpiredInvestment($investment)
    {
        // 返还到出资时的钱包类型
        $returnWalletType = $investment->wallet_type;

        // 获取钱包字段名
        $walletFieldMap = [
            1 => 'topup_balance',
            2 => 'team_bonus_balance',
            3 => 'butie',
            4 => 'balance',
            5 => 'digit_balance',
            6 => 'integral',
            7 => 'appreciating_wallet',
            8 => 'butie_lock',
            9 => 'lottery_tickets',
            10 => 'tiyan_wallet_lock',
            11 => 'tiyan_wallet',
            12 => 'xingfu_tickets'
        ];

        $returnWalletField = $walletFieldMap[$returnWalletType] ?? 'topup_balance';

        Db::startTrans();
        try {
            // 更新出资记录状态
            $investment->status = 3; // 已返还
            $investment->return_time = date('Y-m-d H:i:s');
            $investment->save();

            // 返还本金和利息到用户钱包
            $returnAmount = $investment->total_amount;
            $principalAmount = $investment->investment_amount;
            $interestAmount = $investment->total_interest;

            // 增加用户钱包余额并记录日志
            // 根据钱包类型设置正确的log_type
            $logTypeMap = [
                1 => 1,  // topup_balance -> log_type=1 (余额)
                2 => 2,  // team_bonus_balance -> log_type=2 (荣誉钱包)
                3 => 3,  // butie -> log_type=3 (稳盈钱包)
                4 => 4,  // balance -> log_type=4 (民生钱包)
                5 => 5,  // digit_balance -> log_type=5 (收益钱包)
                6 => 2,  // integral -> log_type=2 (积分)
                7 => 6,  // appreciating_wallet -> log_type=6 (幸福收益)
                8 => 7,  // butie_lock -> log_type=7 (稳赢钱包转入)
                9 => 8,  // lottery_tickets -> log_type=8 (抽奖卷)
                10 => 9, // tiyan_wallet_lock -> log_type=9 (体验钱包预支金)
                11 => 11, // tiyan_wallet -> log_type=11 (体验钱包)
                12 => 10  // xingfu_tickets -> log_type=10 (幸福助力卷)
            ];
            $logType = $logTypeMap[$returnWalletType] ?? 1;
            User::changeInc(
                $investment->user_id, 
                $returnAmount, 
                $returnWalletField, 
                114, // 出资返还
                $investment->id, 
                $logType, 
                '出资到期返还', 
                0, 
                2, 
                'HF'
            );

            // 创建返还记录
            InvestmentReturnRecord::create([
                'investment_id' => $investment->id,
                'user_id' => $investment->user_id,
                'return_amount' => $returnAmount,
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestAmount,
                'wallet_type' => $returnWalletType,
                'return_type' => 1, // 到期返还
                'remark' => '系统自动返还'
            ]);

            Db::commit();

            Log::info("出资自动返还成功", [
                'investment_id' => $investment->id,
                'user_id' => $investment->user_id,
                'return_amount' => $returnAmount,
                'wallet_type' => $returnWalletType
            ]);

        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}

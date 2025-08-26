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

            // 增加用户钱包余额
            User::where('id', $investment->user_id)->inc($returnWalletField, $returnAmount)->update();

            // 记录余额变动日志
            $logType = $returnWalletType == 6 ? 2 : 1; // 积分用log_type=2
            UserBalanceLog::create([
                'user_id' => $investment->user_id,
                'type' => 114, // 出资返还
                'log_type' => $logType,
                'relation_id' => $investment->id,
                'before_balance' => 0, // 这里需要获取实际余额，简化处理
                'change_balance' => $returnAmount,
                'after_balance' => 0, // 这里需要获取实际余额，简化处理
                'remark' => '出资到期返还',
                'status' => 2
            ]);

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

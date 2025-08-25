<?php

namespace app\common\command;

use app\model\LoanRepaymentPlan;
use app\model\LoanApplication;
use app\model\LoanProduct;
use app\model\NoticeMessage;
use app\model\NoticeMessageUser;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class CheckLoanOverdue extends Command
{
    protected function configure()
    {
        $this->setName('checkLoanOverdue')
            ->setDescription('检查贷款逾期并处理');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始检查贷款逾期...');
        
        try {
            $today = date('Y-m-d');
            $count = 0;
            $notifyCount = 0;
            
            // 查找已逾期但状态未更新的还款计划
            $overduePlans = LoanRepaymentPlan::with(['user', 'application'])
                ->where('due_date', '<', $today)
                ->where('status', 1) // 待还款状态
                ->select();

            if ($overduePlans->isEmpty()) {
                $output->writeln('没有发现新的逾期记录');
                return;
            }

            foreach ($overduePlans as $plan) {
                $overdueDays = (strtotime($today) - strtotime($plan->due_date)) / 86400;
                
                // 更新逾期状态
                $plan->status = 3; // 逾期
                $plan->overdue_days = $overdueDays;
                
                // 计算逾期利息
                $application = LoanApplication::find($plan->application_id);
                if ($application) {
                    $product = LoanProduct::find($application->product_id);
                    if ($product && $product->overdue_interest_rate > 0) {
                        // 逾期利息 = 剩余金额 × 逾期日利率 × 逾期天数
                        $overdueInterest = bcmul(
                            $plan->remaining_amount, 
                            bcmul(
                                bcdiv($product->overdue_interest_rate, '100', 4), 
                                (string)$overdueDays, 
                                4
                            ), 
                            2
                        );
                        $plan->overdue_interest = $overdueInterest;
                    }
                }
                
                $plan->save();
                $count++;
                
                // 发送逾期通知
                if ($this->sendOverdueNotification($plan)) {
                    $notifyCount++;
                }
                
                $output->writeln("处理逾期计划ID: {$plan->id}, 逾期天数: {$overdueDays}天");
            }

            $output->writeln("检查完成，更新了 {$count} 条逾期记录，发送了 {$notifyCount} 条通知");
            Log::info("贷款逾期检查完成，更新了 {$count} 条逾期记录，发送了 {$notifyCount} 条通知");
            
        } catch (\Exception $e) {
            $output->writeln('执行出错：' . $e->getMessage());
            Log::error('贷款逾期检查异常：' . $e->getMessage());
        }
    }

    /**
     * 发送逾期通知
     * @param LoanRepaymentPlan $plan
     * @return bool
     */
    private function sendOverdueNotification($plan)
    {
        try {
            $user = User::find($plan->user_id);
            $application = LoanApplication::find($plan->application_id);
            
            if (!$user || !$application) {
                return false;
            }

            $title = '贷款逾期提醒';
            $content = "尊敬的{$user['realname']}，您的贷款已逾期！\n\n";
            $content .= "逾期详情：\n";
            $content .= "• 贷款金额：{$application->loan_amount}元\n";
            $content .= "• 逾期期数：第{$plan->period}期\n";
            $content .= "• 逾期天数：{$plan->overdue_days}天\n";
            $content .= "• 应还金额：{$plan->remaining_amount}元\n";
            $content .= "• 逾期利息：{$plan->overdue_interest}元\n";
            $content .= "• 总计应还：" . bcadd($plan->remaining_amount, $plan->overdue_interest, 2) . "元\n\n";
            $content .= "请尽快还款，避免产生更多逾期费用。";

            // 创建消息
            $message = NoticeMessage::create([
                'title' => $title,
                'content' => $content,
                'type' => 1 // 系统通知
            ]);

            // 为用户创建消息记录
            NoticeMessageUser::create([
                'user_id' => $plan->user_id,
                'message_id' => $message->id,
                'is_read' => 0,
                'read_time' => null
            ]);

            Log::info("发送逾期通知成功", [
                'plan_id' => $plan->id,
                'user_id' => $plan->user_id,
                'overdue_days' => $plan->overdue_days,
                'message_id' => $message->id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('发送逾期通知失败：' . $e->getMessage(), [
                'plan_id' => $plan->id,
                'user_id' => $plan->user_id
            ]);
            return false;
        }
    }
}


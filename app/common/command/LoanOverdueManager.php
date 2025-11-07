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

class LoanOverdueManager extends Command
{
    protected function configure()
    {
        $this->setName('loanOverdueManager')
            ->setDescription('贷款逾期管理工具')
            ->addOption('action', 'a', \think\console\input\Option::VALUE_REQUIRED, '操作类型：check(检查逾期), stats(统计), remind(发送提醒), clean(清理)')
            ->addOption('days', 'd', \think\console\input\Option::VALUE_OPTIONAL, '逾期天数阈值', 1);
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getOption('action');
        $days = (int)$input->getOption('days');

        switch ($action) {
            case 'check':
                $this->checkOverdue($output, $days);
                break;
            case 'stats':
                $this->getOverdueStats($output);
                break;
            case 'remind':
                $this->sendOverdueReminders($output, $days);
                break;
            case 'clean':
                $this->cleanOverdueData($output);
                break;
            default:
                $output->writeln('请指定操作类型：check(检查逾期), stats(统计), remind(发送提醒), clean(清理)');
                break;
        }
    }

    /**
     * 检查逾期
     */
    private function checkOverdue(Output $output, $days = 1)
    {
        $output->writeln("开始检查逾期（逾期天数 >= {$days}天）...");
        
        try {
            $today = date('Y-m-d');
            $count = 0;
            
            // 查找逾期的还款计划（包含首次逾期和已逾期未结清的）
            $overduePlans = LoanRepaymentPlan::with(['user', 'application'])
                ->where('due_date', '<', $today)
                ->whereIn('status', [1, 3]) // 1: 待还款，3: 逾期
                ->where('remaining_amount', '>', 0)
                ->select();

            if ($overduePlans->isEmpty()) {
                $output->writeln('没有需要处理的逾期记录');
                return;
            }

            foreach ($overduePlans as $plan) {
                $overdueDays = (strtotime($today) - strtotime($plan->due_date)) / 86400;
                
                if ($overdueDays < $days) {
                    continue; // 跳过未达到阈值的
                }
                
                // 更新逾期状态
                if ($plan->status != 3) {
                    $plan->status = 3; // 逾期
                }
                $plan->overdue_days = $overdueDays;
                
                // 计算逾期利息
                $application = LoanApplication::find($plan->application_id);
                if ($application) {
                    $product = LoanProduct::find($application->product_id);
                    if ($product && $product->overdue_interest_rate > 0) {
                        // 逾期利息 = 剩余金额 × 逾期日利率 × 逾期天数 ÷ 100
                        // 计算过程中不四舍五入，最后结果才保留两位小数
                        $overdueInterest = bcmul(
                            bcmul(
                                (string)$plan->remaining_amount, 
                                bcdiv($product->overdue_interest_rate, '100', 8), 
                                8
                            ), 
                            (string)$overdueDays, 
                            2
                        );
                        $plan->overdue_interest = $overdueInterest;
                    }
                }
                
                $plan->save();
                $count++;
                
                $output->writeln("处理逾期计划ID: {$plan->id}, 逾期天数: {$overdueDays}天");
            }

            $output->writeln("检查完成，更新了 {$count} 条逾期记录");
            Log::info("贷款逾期检查完成，更新了 {$count} 条逾期记录");
            
        } catch (\Exception $e) {
            $output->writeln('执行出错：' . $e->getMessage());
            Log::error('贷款逾期检查异常：' . $e->getMessage());
        }
    }

    /**
     * 获取逾期统计
     */
    private function getOverdueStats(Output $output)
    {
        $output->writeln('开始统计逾期数据...');
        
        try {
            // 逾期总览
            $overdueCount = LoanRepaymentPlan::where('status', 3)->count();
            $overdueAmount = LoanRepaymentPlan::where('status', 3)->sum('remaining_amount');
            $overdueInterest = LoanRepaymentPlan::where('status', 3)->sum('overdue_interest');
            
            // 逾期天数分布
            $overdueDistribution = [
                '1-7天' => LoanRepaymentPlan::where('status', 3)->whereBetween('overdue_days', [1, 7])->count(),
                '8-30天' => LoanRepaymentPlan::where('status', 3)->whereBetween('overdue_days', [8, 30])->count(),
                '31-90天' => LoanRepaymentPlan::where('status', 3)->whereBetween('overdue_days', [31, 90])->count(),
                '90天以上' => LoanRepaymentPlan::where('status', 3)->where('overdue_days', '>', 90)->count(),
            ];
            
            // 逾期用户统计
            $overdueUserCount = LoanRepaymentPlan::where('status', 3)->group('user_id')->count();
            
            $output->writeln("=== 逾期统计报告 ===");
            $output->writeln("逾期计划总数：{$overdueCount}");
            $output->writeln("逾期用户总数：{$overdueUserCount}");
            $output->writeln("逾期本金总额：{$overdueAmount}元");
            $output->writeln("逾期利息总额：{$overdueInterest}元");
            $output->writeln("逾期总金额：" . bcadd($overdueAmount, $overdueInterest, 2) . "元");
            $output->writeln("");
            $output->writeln("=== 逾期天数分布 ===");
            foreach ($overdueDistribution as $range => $count) {
                $output->writeln("{$range}：{$count}条");
            }
            
            Log::info("逾期统计完成", [
                'overdue_count' => $overdueCount,
                'overdue_amount' => $overdueAmount,
                'overdue_interest' => $overdueInterest,
                'overdue_user_count' => $overdueUserCount
            ]);
            
        } catch (\Exception $e) {
            $output->writeln('统计出错：' . $e->getMessage());
            Log::error('逾期统计异常：' . $e->getMessage());
        }
    }

    /**
     * 发送逾期提醒
     */
    private function sendOverdueReminders(Output $output, $days = 1)
    {
        $output->writeln("开始发送逾期提醒（逾期天数 >= {$days}天）...");
        
        try {
            $count = 0;
            
            // 查找逾期计划
            $overduePlans = LoanRepaymentPlan::with(['user', 'application'])
                ->where('status', 3)
                ->where('overdue_days', '>=', $days)
                ->select();

            if ($overduePlans->isEmpty()) {
                $output->writeln('没有需要发送提醒的逾期记录');
                return;
            }

            foreach ($overduePlans as $plan) {
                if ($this->sendOverdueNotification($plan)) {
                    $count++;
                    $output->writeln("发送提醒成功 - 计划ID: {$plan->id}, 用户: {$plan->user->phone}");
                }
            }

            $output->writeln("提醒发送完成，成功发送 {$count} 条提醒");
            Log::info("逾期提醒发送完成，成功发送 {$count} 条提醒");
            
        } catch (\Exception $e) {
            $output->writeln('发送提醒出错：' . $e->getMessage());
            Log::error('逾期提醒发送异常：' . $e->getMessage());
        }
    }

    /**
     * 清理逾期数据（仅用于测试）
     */
    private function cleanOverdueData(Output $output)
    {
        $output->writeln('开始清理逾期数据（仅用于测试环境）...');
        
        try {
            // 重置逾期状态为待还款
            $count = LoanRepaymentPlan::where('status', 3)
                ->update([
                    'status' => 1,
                    'overdue_days' => 0,
                    'overdue_interest' => 0
                ]);

            $output->writeln("清理完成，重置了 {$count} 条逾期记录");
            Log::info("逾期数据清理完成，重置了 {$count} 条逾期记录");
            
        } catch (\Exception $e) {
            $output->writeln('清理出错：' . $e->getMessage());
            Log::error('逾期数据清理异常：' . $e->getMessage());
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

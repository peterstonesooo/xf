<?php

namespace app\common\command;

use app\model\LoanApplication;
use app\model\LoanRepaymentPlan;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class RepairLoanApplicationStatus extends Command
{
    protected function configure()
    {
        $this->setName('repairLoanApplicationStatus')
            ->setDescription('修复贷款申请状态：已还清所有期数但状态仍为4的申请，更新为5（已结清）');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始检查并修复贷款申请状态...');
        
        try {
            // 查找所有状态为4（已放款）的申请
            $applications = LoanApplication::where('status', 4)->select();

            if ($applications->isEmpty()) {
                $output->writeln('没有找到状态为4的贷款申请');
                return;
            }

            $totalCount = 0;
            $fixedCount = 0;
            $skippedCount = 0;

            foreach ($applications as $application) {
                $totalCount++;
                
                $output->writeln("检查申请ID: {$application->id}");
                
                // 检查该申请的所有还款计划
                $allPlans = LoanRepaymentPlan::where('application_id', $application->id)->select();
                
                if ($allPlans->isEmpty()) {
                    $output->writeln("  - 警告：申请ID {$application->id} 没有还款计划");
                    $skippedCount++;
                    continue;
                }
                
                // 检查是否所有期数都已还清
                $allPaid = true;
                $statusSummary = [];
                
                foreach ($allPlans as $plan) {
                    if (!isset($statusSummary[$plan->status])) {
                        $statusSummary[$plan->status] = 0;
                    }
                    $statusSummary[$plan->status]++;
                    
                    if ($plan->status != 2) {
                        $allPaid = false;
                    }
                }
                
                // 输出还款计划状态统计
                $statusText = [];
                foreach ($statusSummary as $status => $count) {
                    $statusName = ['1' => '待还款', '2' => '已还款', '3' => '逾期'][$status] ?? '未知';
                    $statusText[] = "{$statusName}({$count}期)";
                }
                $output->writeln("  - 还款计划状态: " . implode(', ', $statusText));
                
                // 如果所有期数都已还清，更新申请状态
                if ($allPaid) {
                    try {
                        $application->status = 5; // 已结清
                        $application->save();
                        $fixedCount++;
                        $output->writeln("  ✓ 申请ID {$application->id} 状态已更新为5（已结清）");
                        
                        Log::info('修复贷款申请状态', [
                            'application_id' => $application->id,
                            'user_id' => $application->user_id,
                            'old_status' => 4,
                            'new_status' => 5
                        ]);
                    } catch (\Exception $e) {
                        $output->writeln("  ✗ 申请ID {$application->id} 更新失败: " . $e->getMessage());
                    }
                } else {
                    $output->writeln("  - 申请ID {$application->id} 还有未还清的期数，跳过");
                    $skippedCount++;
                }
            }

            $output->writeln('');
            $output->writeln("=====================================");
            $output->writeln("修复完成！");
            $output->writeln("检查申请总数: {$totalCount}");
            $output->writeln("已修复: {$fixedCount}");
            $output->writeln("跳过: {$skippedCount}");
            $output->writeln("=====================================");
            
        } catch (\Exception $e) {
            $output->writeln('执行失败：' . $e->getMessage());
            Log::error('修复贷款申请状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}


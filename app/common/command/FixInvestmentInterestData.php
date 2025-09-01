<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\model\InvestmentRecord;
use app\model\InvestmentGradient;
use think\facade\Db;
use Exception;

class FixInvestmentInterestData extends Command
{
    protected function configure()
    {
        $this->setName('fix:investment_interest_data')
             ->setDescription('修复线上出资数据的利息计算');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始修复线上出资数据利息计算...');
        
        try {
            // 获取所有进行中的出资记录
            $investments = InvestmentRecord::with(['gradient'])
                                         ->where('status', 1) // 只处理进行中的记录
                                         ->select();
            
            $output->writeln("找到 " . count($investments) . " 条进行中的出资记录");
            
            $updatedCount = 0;
            $errorCount = 0;
            $skippedCount = 0;
            
            foreach ($investments as $investment) {
                try {
                    $output->writeln("处理出资记录ID: {$investment['id']}");
                    $output->writeln("  用户ID: {$investment['user_id']}");
                    $output->writeln("  出资金额: {$investment['investment_amount']} (类型: " . gettype($investment['investment_amount']) . ")");
                    $output->writeln("  当前利率: {$investment['interest_rate']} (类型: " . gettype($investment['interest_rate']) . ")");
                    $output->writeln("  出资天数: {$investment['investment_days']} (类型: " . gettype($investment['investment_days']) . ")");
                    $output->writeln("  当前总利息: {$investment['total_interest']}");
                    $output->writeln("  当前总金额: {$investment['total_amount']}");
                    
                    // 根据gradient_id查找正确的利率和天数
                    $gradient = InvestmentGradient::where('id', $investment['gradient_id'])->find();
                    if (!$gradient) {
                        $output->writeln("  ✗ 找不到对应的梯度配置，跳过此记录");
                        $skippedCount++;
                        continue;
                    }
                    
                    $output->writeln("  梯度信息:");
                    $output->writeln("    梯度名称: {$gradient['name']}");
                    $output->writeln("    正确利率: {$gradient['interest_rate']}");
                    $output->writeln("    正确天数: {$gradient['investment_days']}");
                    
                    // 使用正确的公式重新计算利息
                    // 总利息 = 出资金额 × (利率/100) × 出资天数
                    $newTotalInterest = bcmul(
                        bcmul(
                            (string)$investment['investment_amount'], 
                            bcdiv($gradient['interest_rate'], '100', 4), 
                            4
                        ), 
                        (string)$gradient['investment_days'], 
                        2
                    );
                    
                    $newTotalAmount = bcadd((string)$investment['investment_amount'], $newTotalInterest, 2);
                    
                    $output->writeln("  计算结果:");
                    $output->writeln("    新总利息: {$newTotalInterest}");
                    $output->writeln("    新总金额: {$newTotalAmount}");
                    
                    $output->writeln("  新总利息: {$newTotalInterest}");
                    $output->writeln("  新总金额: {$newTotalAmount}");
                    
                    // 检查是否需要更新
                    $interestDiff = bcsub($newTotalInterest, $investment['total_interest'], 2);
                    $amountDiff = bcsub($newTotalAmount, $investment['total_amount'], 2);
                    
                    if (bccomp($interestDiff, '0', 2) == 0 && bccomp($amountDiff, '0', 2) == 0) {
                        $output->writeln("  ✓ 数据已正确，无需更新");
                        $skippedCount++;
                    } else {
                        $output->writeln("  ! 利息差异: {$interestDiff}");
                        $output->writeln("  ! 金额差异: {$amountDiff}");
                        
                        // 询问是否更新
                        $output->writeln("  是否更新此记录? (y/n): ");
                        $handle = fopen("php://stdin", "r");
                        $line = fgets($handle);
                        fclose($handle);
                        
                        if (trim($line) === 'y' || trim($line) === 'Y') {
                            Db::startTrans();
                            try {
                                // 更新出资记录
                                InvestmentRecord::where('id', $investment['id'])->update([
                                    'total_interest' => $newTotalInterest,
                                    'total_amount' => $newTotalAmount,
                                    'interest_rate' => $gradient['interest_rate'],
                                    'investment_days' => $gradient['investment_days']
                                ]);
                                
                                // 记录修复日志
                                $this->logFixRecord($investment, $newTotalInterest, $newTotalAmount, $output);
                                
                                Db::commit();
                                $updatedCount++;
                                $output->writeln("  ✓ 更新成功！");
                            } catch (Exception $e) {
                                Db::rollback();
                                $errorCount++;
                                $output->writeln("  ✗ 更新失败: " . $e->getMessage());
                            }
                        } else {
                            $output->writeln("  跳过更新");
                            $skippedCount++;
                        }
                    }
                    
                } catch (Exception $e) {
                    $errorCount++;
                    $output->writeln("  ✗ 处理失败: " . $e->getMessage());
                }
                
                $output->writeln("");
            }
            
            $output->writeln("修复完成！");
            $output->writeln("更新成功: {$updatedCount} 条");
            $output->writeln("跳过更新: {$skippedCount} 条");
            $output->writeln("更新失败: {$errorCount} 条");
            
            // 生成修复报告
            $this->generateFixReport($output);
            
        } catch (Exception $e) {
            $output->writeln("修复失败: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    /**
     * 记录修复日志
     */
    private function logFixRecord($investment, $newTotalInterest, $newTotalAmount, $output)
    {
        try {
            $logData = [
                'investment_id' => $investment['id'],
                'user_id' => $investment['user_id'],
                'old_total_interest' => $investment['total_interest'],
                'new_total_interest' => $newTotalInterest,
                'old_total_amount' => $investment['total_amount'],
                'new_total_amount' => $newTotalAmount,
                'fix_time' => date('Y-m-d H:i:s'),
                'fix_type' => 'interest_calculation'
            ];
            
            // 可以记录到日志文件或数据库
            $output->writeln("  📝 记录修复日志: " . json_encode($logData, JSON_UNESCAPED_UNICODE));
            
        } catch (Exception $e) {
            $output->writeln("  ⚠️ 记录修复日志失败: " . $e->getMessage());
        }
    }
    
    /**
     * 生成修复报告
     */
    private function generateFixReport($output)
    {
        try {
            // 统计修复后的数据
            $totalInvestments = InvestmentRecord::where('status', 1)->count();
            $totalAmount = InvestmentRecord::where('status', 1)->sum('investment_amount');
            $totalInterest = InvestmentRecord::where('status', 1)->sum('total_interest');
            $totalReturn = InvestmentRecord::where('status', 1)->sum('total_amount');
            
            $output->writeln("");
            $output->writeln("=== 修复报告 ===");
            $output->writeln("进行中的出资记录总数: {$totalInvestments}");
            $output->writeln("总出资金额: {$totalAmount}");
            $output->writeln("总利息金额: {$totalInterest}");
            $output->writeln("总返还金额: {$totalReturn}");
            $output->writeln("修复时间: " . date('Y-m-d H:i:s'));
            $output->writeln("================");
            
        } catch (Exception $e) {
            $output->writeln("生成修复报告失败: " . $e->getMessage());
        }
    }
}

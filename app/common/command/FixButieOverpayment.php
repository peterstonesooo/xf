<?php

namespace app\common\command;

use app\model\Order;
use app\model\User;
use app\model\UserBalanceLog;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class FixButieOverpayment extends Command
{
    protected function configure()
    {
        $this->setName('fix:butie-overpayment')
             ->setDescription('修复Butie.php中产品60-69多发的digit_balance数据');
    }

    public function execute(Input $input, Output $output)
    {
        ini_set("memory_limit", "-1");
        set_time_limit(0);
        
        $output->writeln("开始修复Butie.php中产品60-69多发的balance和butie钱包数据（每次处理10条记录）...");
        
        try {
            // 查找产品60-69相关的资金明细记录（balance和butie钱包），每次只处理10条
            $balanceLogs = UserBalanceLog::where('type', 59)
                                        ->whereIn('log_type', [3, 4]) // 3=butie钱包, 4=balance钱包
                                        ->where('remark', '购买商品每周分红')
                                        ->whereIn('relation_id', function($query) {
                                            $query->name('order')
                                                  ->where('project_id', '>=', 60)
                                                  ->where('project_id', '<=', 69)
                                                  ->field('id');
                                        })
                                        ->limit(10)
                                        ->select();
            
            $output->writeln("找到 " . count($balanceLogs) . " 条需要处理的资金明细记录（本次处理前10条）");
            
            $successCount = 0;
            $failCount = 0;
            $insufficientBalanceCount = 0;
            $errors = [];
            
            foreach ($balanceLogs as $balanceLog) {
                $output->writeln("处理资金明细ID: {$balanceLog->id}, 用户ID: {$balanceLog->user_id}, 金额: {$balanceLog->change_balance}, 钱包类型: {$balanceLog->log_type}");
                
                // 检查用户是否存在
                $user = User::find($balanceLog->user_id);
                if (!$user) {
                    $output->writeln("  用户不存在，跳过");
                    $failCount++;
                    continue;
                }
                
                // 确定钱包字段
                $walletField = '';
                switch ($balanceLog->log_type) {
                    case 3: // butie钱包
                        $walletField = 'butie';
                        break;
                    case 4: // balance钱包
                        $walletField = 'balance';
                        break;
                    default:
                        $output->writeln("  未知的钱包类型，跳过");
                        $failCount++;
                        continue 2;
                }
                
                $currentBalance = $user->$walletField;
                $deductAmount = $balanceLog->change_balance;
                
                $output->writeln("  用户当前{$walletField}余额: {$currentBalance}");
                $output->writeln("  需要扣除金额: {$deductAmount}");
                
                Db::startTrans();
                try {
                    if ($currentBalance >= $deductAmount) {
                        // 余额足够，直接扣除
                        User::where('id', $balanceLog->user_id)
                            ->dec($walletField, $deductAmount)
                            ->update();
                        
                        // 删除资金明细记录
                        UserBalanceLog::where('id', $balanceLog->id)->delete();
                        
                        $output->writeln("  ✅ 成功扣除 {$deductAmount} 元，删除资金明细记录");
                        $successCount++;
                        
                        // 记录日志
                        Log::info("修复Butie多发数据成功", [
                            'user_id' => $balanceLog->user_id,
                            'balance_log_id' => $balanceLog->id,
                            'wallet_field' => $walletField,
                            'deduct_amount' => $deductAmount,
                            'relation_id' => $balanceLog->relation_id
                        ]);
                        
                    } else {
                        // 余额不足，只能扣除现有余额
                        $actualDeductAmount = $currentBalance;
                        
                        if ($actualDeductAmount > 0) {
                            User::where('id', $balanceLog->user_id)
                                ->dec($walletField, $actualDeductAmount)
                                ->update();
                            
                            // 删除资金明细记录
                            UserBalanceLog::where('id', $balanceLog->id)->delete();
                            
                            $output->writeln("  ⚠️  余额不足，只扣除 {$actualDeductAmount} 元，删除资金明细记录");
                            $successCount++;
                        } else {
                            // 余额为0，只删除资金明细记录
                            UserBalanceLog::where('id', $balanceLog->id)->delete();
                            
                            $output->writeln("  ⚠️  余额为0，只删除资金明细记录");
                            $successCount++;
                        }
                        
                        $insufficientBalanceCount++;
                        
                        // 记录余额不足的日志
                        Log::warning("修复Butie多发数据-余额不足", [
                            'user_id' => $balanceLog->user_id,
                            'balance_log_id' => $balanceLog->id,
                            'wallet_field' => $walletField,
                            'required_amount' => $deductAmount,
                            'current_balance' => $currentBalance,
                            'actual_deducted' => $actualDeductAmount,
                            'relation_id' => $balanceLog->relation_id
                        ]);
                    }
                    
                    Db::commit();
                    
                } catch (\Exception $e) {
                    Db::rollback();
                    $errorMsg = "处理资金明细 {$balanceLog->id} 失败: " . $e->getMessage();
                    $output->writeln("  ❌ " . $errorMsg);
                    $errors[] = $errorMsg;
                    $failCount++;
                    
                    Log::error("修复Butie多发数据失败", [
                        'user_id' => $balanceLog->user_id,
                        'balance_log_id' => $balanceLog->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // 输出统计结果
            $output->writeln("\n=== 修复完成 ===");
            $output->writeln("成功处理: {$successCount} 个");
            $output->writeln("处理失败: {$failCount} 个");
            $output->writeln("余额不足: {$insufficientBalanceCount} 个");
            
            if (!empty($errors)) {
                $output->writeln("\n错误详情:");
                foreach ($errors as $error) {
                    $output->writeln("- {$error}");
                }
            }
            
            // 记录最终统计日志
            Log::info("修复Butie多发数据完成", [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'insufficient_balance_count' => $insufficientBalanceCount,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            $output->writeln("脚本执行失败: " . $e->getMessage());
            Log::error("修复Butie多发数据脚本失败", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

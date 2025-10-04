<?php

namespace app\common\command;

use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use Exception;

class MigrateUserWallet extends Command
{
    protected function configure()
    {
        $this->setName('migrateUserWallet')->setDescription('迁移用户钱包新的共富钱包=稳盈钱包+惠民钱包');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始迁移用户钱包新的共富钱包=稳盈钱包+惠民钱包...');
        
        $successCount = 0;
        $failCount = 0;
        $skipCount = 0;

        try {
            // 使用 chunk 分批处理用户数据
            User::field('id,butie,digit_balance,gongfu_wallet')->chunk(500, function($users) use (&$successCount, &$failCount, &$skipCount, $output) {
                foreach ($users as $user) {
                    try {
                        // 计算需要迁移的总金额：稳盈钱包 + 惠民钱包
                        $butieAmount = floatval($user['butie'] ?? 0);
                        $digitBalanceAmount = floatval($user['digit_balance'] ?? 0);
                        $totalAmount = $butieAmount + $digitBalanceAmount;
                        
                        // 如果总金额为0，跳过此用户
                        if ($totalAmount <= 0) {
                            $skipCount++;
                            continue;
                        }
                        
                        // 使用公共方法进行钱包迁移
                        // 清零稳盈钱包并记录日志
                        if ($butieAmount > 0) {
                            User::changeInc($user['id'], -$butieAmount, 'butie', 123, 0, 3, '钱包迁移：稳盈钱包转入共富钱包', 0, 2, 'QBQY', 1);
                        }
                        
                        // 清零惠民钱包并记录日志
                        if ($digitBalanceAmount > 0) {
                            User::changeInc($user['id'], -$digitBalanceAmount, 'digit_balance', 123, 0, 5, '钱包迁移：惠民钱包转入共富钱包', 0, 2, 'QBQY', 1);
                        }
                        
                        // 转入共富钱包并记录日志
                        if ($totalAmount > 0) {
                            User::changeInc($user['id'], $totalAmount, 'gongfu_wallet', 123, 0, 16, '钱包迁移：稳盈钱包+惠民钱包转入共富钱包', 0, 2, 'QBQY', 1);
                        }
                        
                        $successCount++;
                        $output->writeln("用户 {$user['id']} 钱包迁移成功，转入共富钱包金额: {$totalAmount}（稳盈: {$butieAmount} + 惠民: {$digitBalanceAmount}）");
                        
                    } catch (Exception $e) {
                        $failCount++;
                        $output->writeln("用户 {$user['id']} 处理失败: " . $e->getMessage());
                    }
                }
            });

            $output->writeln("迁移完成！");
            $output->writeln("成功: {$successCount} 个用户");
            $output->writeln("跳过: {$skipCount} 个用户");
            $output->writeln("失败: {$failCount} 个用户");

        } catch (Exception $e) {
            $output->writeln("迁移失败: " . $e->getMessage());
        }
    }
}

<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\User;
use app\model\UserBalanceLog;

/**
 * 修复购买商品到期分红错误发放的资金
 * 
 * 功能：
 * 1. 查询错误发放的记录（type=59, log_type=5, remark="购买商品到期分红", project_id=70）
 * 2. 将digit_balance钱包减少change_balance金额
 * 3. 将puhui钱包增加change_balance金额
 * 4. 修改mp_user_balance_log表中remark为"购买商品到期分红-修复"
 */
class RepairBalanceLog extends Command
{
    protected function configure()
    {
        $this->setName('repair:balance-log')
            ->setDescription('修复购买商品到期分红错误发放的资金')
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
                $output->writeln('<info>=== 开始修复购买商品到期分红错误发放的资金 ===</info>');
            }
            
            // 查询需要修复的记录
            $sql = "SELECT u.phone, o.project_name, ub.* 
                    FROM mp_user_balance_log as ub 
                    LEFT JOIN mp_order AS o ON(ub.user_id=o.user_id) 
                    LEFT JOIN mp_user AS u ON(u.id = o.user_id)
                    WHERE ub.type=59 AND ub.log_type=5 AND ub.remark='购买商品到期分红' 
                    AND o.project_id=70 AND ub.created_at > '2025-09-01 00:00:06'";
            
            $errorRecords = Db::query($sql);
            
            if (empty($errorRecords)) {
                $output->writeln('<info>没有找到需要修复的记录</info>');
                return;
            }
            
            $output->writeln('<info>找到 ' . count($errorRecords) . ' 条需要修复的记录</info>');
            
            // 显示记录详情
            $output->writeln('');
            $output->writeln('<comment>记录详情：</comment>');
            $output->writeln(str_repeat('-', 100));
            $output->writeln(sprintf('%-10s %-15s %-20s %-15s %-20s %-15s', 
                       'ID', '用户ID', '手机号', '项目名称', '金额', '创建时间'));
            $output->writeln(str_repeat('-', 100));
            
            $totalAmount = 0;
            foreach ($errorRecords as $record) {
                $output->writeln(sprintf('%-10s %-15s %-20s %-20s %-15s %-20s', 
                           $record['id'], 
                           $record['user_id'], 
                           $record['phone'], 
                           $record['project_name'], 
                           $record['change_balance'], 
                           $record['created_at']));
                $totalAmount += $record['change_balance'];
            }
            $output->writeln(str_repeat('-', 100));
            $output->writeln('<comment>总金额: ' . $totalAmount . '</comment>');
            $output->writeln('');
            
            if ($isDryRun) {
                $output->writeln('<info>预览完成。如需执行修复，请使用 --confirm 参数。</info>');
                return;
            }
            
            if (!$isConfirmed) {
                $output->writeln('<error>警告：此操作将修改用户余额数据！</error>');
                $output->writeln('<error>请确认后使用 --confirm 参数执行修复操作。</error>');
                return;
            }
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($errorRecords as $record) {
                try {
                    $output->writeln('<comment>处理用户ID: ' . $record['user_id'] . ', 手机号: ' . $record['phone'] . ', 金额: ' . $record['change_balance'] . '</comment>');
                    
                    // 验证用户是否存在
                    $user = User::where('id', $record['user_id'])->find();
                    if (!$user) {
                        throw new \Exception("用户不存在");
                    }
                    
                    // 验证digit_balance余额是否足够
                    if ($user['digit_balance'] < $record['change_balance']) {
                        throw new \Exception("digit_balance余额不足，当前余额: {$user['digit_balance']}, 需要扣除: {$record['change_balance']}");
                    }
                    
                    // 开始事务
                    Db::startTrans();
                    
                    // 1. 减少digit_balance钱包金额
                    User::changeInc(
                        $record['user_id'], 
                        -$record['change_balance'],  // 负数表示减少
                        'digit_balance', 
                        59,  // type
                        $record['relation_id'], 
                        5,   // log_type
                        '购买商品到期分红-修复-减少digit_balance', 
                        0,   // admin_user_id
                        2,   // status
                        'XF' // sn_prefix
                    );
                    
                    // 2. 增加puhui钱包金额
                    User::changeInc(
                        $record['user_id'], 
                        $record['change_balance'],   // 正数表示增加
                        'puhui', 
                        59,  // type
                        $record['relation_id'], 
                        13,  // log_type (puhui钱包对应log_type=13)
                        '购买商品到期分红-修复-增加puhui', 
                        0,   // admin_user_id
                        2,   // status
                        'XF' // sn_prefix
                    );
                    
                    // 3. 修改原记录的remark
                    UserBalanceLog::where('id', $record['id'])
                        ->update(['remark' => '购买商品到期分红-修复']);
                    
                    // 提交事务
                    Db::commit();
                    
                    $successCount++;
                    $output->writeln('<info>✓ 修复成功</info>');
                    
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    
                    $errorCount++;
                    $output->writeln('<error>✗ 修复失败: ' . $e->getMessage() . '</error>');
                }
                
                $output->writeln('---');
            }
            
            $output->writeln('');
            $output->writeln('<info>修复完成！</info>');
            $output->writeln('<info>成功: ' . $successCount . ' 条</info>');
            $output->writeln('<error>失败: ' . $errorCount . ' 条</error>');
            
        } catch (\Exception $e) {
            $output->writeln('<error>脚本执行出错: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>错误文件: ' . $e->getFile() . '</error>');
            $output->writeln('<error>错误行号: ' . $e->getLine() . '</error>');
        }
    }
}

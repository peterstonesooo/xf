<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\User;
use app\model\UserBalanceLog;

/**
 * 修复之前错误修复的购买商品到期分红数据
 * 
 * 功能：
 * 1. 查询remark='购买商品到期分红-修复'的记录
 * 2. 检查这些记录对应的订单是否真的是项目ID=70
 * 3. 如果不是项目ID=70，说明之前修复错了，需要恢复：
 *    - 将puhui钱包减少change_balance金额
 *    - 将digit_balance钱包增加change_balance金额
 *    - 修改remark为'购买商品到期分红-错误修复已恢复'
 */
class RepairBalanceLogError extends Command
{
    protected function configure()
    {
        $this->setName('repair:balance-log-error')
            ->setDescription('修复之前错误修复的购买商品到期分红数据')
            ->addOption('dry-run', null, \think\console\input\Option::VALUE_NONE, '仅查看需要修复的记录，不执行修复操作')
            ->addOption('confirm', null, \think\console\input\Option::VALUE_NONE, '确认执行修复操作');
    }

    protected function execute(Input $input, Output $output)
    {
        $isDryRun = $input->getOption('dry-run');
        $isConfirmed = $input->getOption('confirm');

        try {
            if ($isDryRun) {
                $output->writeln('<info>=== 预览模式：仅查看需要修复的错误记录 ===</info>');
            } else {
                $output->writeln('<info>=== 开始修复之前错误修复的购买商品到期分红数据 ===</info>');
            }
            
            // 查询所有remark='购买商品到期分红-修复'的记录
            $sql = "SELECT ub.*, u.phone
                    FROM mp_user_balance_log as ub 
                    LEFT JOIN mp_user AS u ON(u.id = ub.user_id)
                    WHERE ub.type=59 AND ub.log_type=5 AND ub.remark='购买商品到期分红-修复'";
            
            $allRecords = Db::query($sql);
            
            if (empty($allRecords)) {
                $output->writeln('<info>没有找到需要检查的记录</info>');
                return;
            }
            
            $output->writeln('<info>找到 ' . count($allRecords) . ' 条已修复的记录，正在检查是否需要恢复...</info>');
            
            // 筛选出错误修复的记录（不是项目ID=70的订单）
            $errorRecords = [];
            foreach ($allRecords as $record) {
                // 通过relation_id查询对应的订单，检查project_id是否为70
                $order = Db::name('order')->where('id', $record['relation_id'])->find();
                if ($order && $order['project_id'] != 70) {
                    $record['project_name'] = $order['project_name'];
                    $record['project_id'] = $order['project_id'];
                    $errorRecords[] = $record;
                }
            }
            
            if (empty($errorRecords)) {
                $output->writeln('<info>没有找到错误修复的记录，所有修复都是正确的</info>');
                return;
            }
            
            $output->writeln('<error>找到 ' . count($errorRecords) . ' 条错误修复的记录需要恢复</error>');
            
            // 显示记录详情
            $output->writeln('');
            $output->writeln('<comment>错误修复记录详情：</comment>');
            $output->writeln(str_repeat('-', 120));
            $output->writeln(sprintf('%-10s %-15s %-20s %-15s %-20s %-15s %-15s', 
                       'ID', '用户ID', '手机号', '项目ID', '项目名称', '金额', '创建时间'));
            $output->writeln(str_repeat('-', 120));
            
            $totalAmount = 0;
            foreach ($errorRecords as $record) {
                $output->writeln(sprintf('%-10s %-15s %-20s %-15s %-20s %-15s %-20s', 
                           $record['id'], 
                           $record['user_id'], 
                           $record['phone'], 
                           $record['project_id'], 
                           $record['project_name'], 
                           $record['change_balance'], 
                           $record['created_at']));
                $totalAmount += $record['change_balance'];
            }
            $output->writeln(str_repeat('-', 120));
            $output->writeln('<comment>总金额: ' . $totalAmount . '</comment>');
            $output->writeln('');
            
            if ($isDryRun) {
                $output->writeln('<info>预览完成。如需执行恢复，请使用 --confirm 参数。</info>');
                return;
            }
            
            if (!$isConfirmed) {
                $output->writeln('<error>警告：此操作将修改用户余额数据！</error>');
                $output->writeln('<error>请确认后使用 --confirm 参数执行恢复操作。</error>');
                return;
            }
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($errorRecords as $record) {
                try {
                    $output->writeln('<comment>恢复用户ID: ' . $record['user_id'] . ', 手机号: ' . $record['phone'] . ', 金额: ' . $record['change_balance'] . ', 项目ID: ' . $record['project_id'] . '</comment>');
                    
                    // 验证用户是否存在
                    $user = User::where('id', $record['user_id'])->find();
                    if (!$user) {
                        throw new \Exception("用户不存在");
                    }
                    
                    // 验证puhui余额是否足够
                    if ($user['puhui'] < $record['change_balance']) {
                        throw new \Exception("puhui余额不足，当前余额: {$user['puhui']}, 需要扣除: {$record['change_balance']}");
                    }
                    
                    // 开始事务
                    Db::startTrans();
                    
                    // 1. 减少puhui钱包金额（恢复之前错误增加的）
                    User::changeInc(
                        $record['user_id'], 
                        -$record['change_balance'],  // 负数表示减少
                        'puhui', 
                        59,  // type
                        $record['relation_id'], 
                        13,  // log_type (puhui钱包对应log_type=13)
                        '购买商品到期分红-错误修复恢复-减少puhui', 
                        0,   // admin_user_id
                        2,   // status
                        'XF' // sn_prefix
                    );
                    
                    // 2. 增加digit_balance钱包金额（恢复之前错误减少的）
                    User::changeInc(
                        $record['user_id'], 
                        $record['change_balance'],   // 正数表示增加
                        'digit_balance', 
                        59,  // type
                        $record['relation_id'], 
                        5,   // log_type (digit_balance钱包对应log_type=5)
                        '购买商品到期分红-错误修复恢复-增加digit_balance', 
                        0,   // admin_user_id
                        2,   // status
                        'XF' // sn_prefix
                    );
                    
                    // 3. 修改原记录的remark
                    UserBalanceLog::where('id', $record['id'])
                        ->update(['remark' => '购买商品到期分红-错误修复已恢复']);
                    
                    // 提交事务
                    Db::commit();
                    
                    $successCount++;
                    $output->writeln('<info>✓ 恢复成功</info>');
                    
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    
                    $errorCount++;
                    $output->writeln('<error>✗ 恢复失败: ' . $e->getMessage() . '</error>');
                }
                
                $output->writeln('---');
            }
            
            $output->writeln('');
            $output->writeln('<info>恢复完成！</info>');
            $output->writeln('<info>成功: ' . $successCount . ' 条</info>');
            $output->writeln('<error>失败: ' . $errorCount . ' 条</error>');
            
        } catch (\Exception $e) {
            $output->writeln('<error>脚本执行出错: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>错误文件: ' . $e->getFile() . '</error>');
            $output->writeln('<error>错误行号: ' . $e->getLine() . '</error>');
        }
    }
}

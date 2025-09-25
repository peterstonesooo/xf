<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\User;
use app\model\UserBalanceLog;

/**
 * 恢复所有购买商品到期分红的修复记录
 * 
 * 功能：
 * 1. 查询remark='购买商品到期分红-修复-增加puhui'的记录
 * 2. 查询remark='购买商品到期分红-修复-减少digit_balance'的记录
 * 3. 将所有这两种记录都进行恢复：
 *    - 对于'增加puhui'的记录：减少puhui钱包金额
 *    - 对于'减少digit_balance'的记录：增加digit_balance钱包金额
 *    - 修改remark为'购买商品到期分红-错误修复已恢复'
 */
class RepairBalanceLogError extends Command
{
    protected function configure()
    {
        $this->setName('repair:balance-log-error')
            ->setDescription('恢复所有购买商品到期分红的修复记录')
            ->addOption('dry-run', null, \think\console\input\Option::VALUE_NONE, '仅查看需要修复的记录，不执行修复操作')
            ->addOption('confirm', null, \think\console\input\Option::VALUE_NONE, '确认执行修复操作');
    }

    protected function execute(Input $input, Output $output)
    {
        $isDryRun = $input->getOption('dry-run');
        $isConfirmed = $input->getOption('confirm');

        try {
            if ($isDryRun) {
                $output->writeln('<info>=== 预览模式：仅查看需要恢复的修复记录 ===</info>');
            } else {
                $output->writeln('<info>=== 开始恢复所有购买商品到期分红的修复记录 ===</info>');
            }
            
            // 查询所有错误修复的记录
            $sql = "SELECT ub.*, u.phone
                    FROM mp_user_balance_log as ub 
                    LEFT JOIN mp_user AS u ON(u.id = ub.user_id)
                    WHERE ub.type=59 AND (
                        ub.remark='购买商品到期分红-修复-增加puhui' OR 
                        ub.remark='购买商品到期分红-修复-减少digit_balance'
                    )";
            
            $allRecords = Db::query($sql);
            
            if (empty($allRecords)) {
                $output->writeln('<info>没有找到需要检查的记录</info>');
                return;
            }
            
            $output->writeln('<info>找到 ' . count($allRecords) . ' 条修复记录，准备恢复...</info>');
            
            // 所有记录都需要恢复（不管项目ID是什么）
            $errorRecords = [];
            foreach ($allRecords as $record) {
                // 通过relation_id查询对应的订单，获取项目信息
                $order = Db::name('order')->where('id', $record['relation_id'])->find();
                if ($order) {
                    $record['project_name'] = $order['project_name'];
                    $record['project_id'] = $order['project_id'];
                } else {
                    $record['project_name'] = '订单不存在';
                    $record['project_id'] = 'N/A';
                }
                $errorRecords[] = $record;
            }
            
            if (empty($errorRecords)) {
                $output->writeln('<info>没有找到需要恢复的记录</info>');
                return;
            }
            
            $output->writeln('<error>找到 ' . count($errorRecords) . ' 条修复记录需要恢复</error>');
            
            // 显示记录详情
            $output->writeln('');
            $output->writeln('<comment>修复记录详情：</comment>');
            $output->writeln(str_repeat('-', 140));
            $output->writeln(sprintf('%-10s %-15s %-20s %-15s %-20s %-15s %-30s %-15s', 
                       'ID', '用户ID', '手机号', '项目ID', '项目名称', '金额', '备注', '创建时间'));
            $output->writeln(str_repeat('-', 140));
            
            $totalAmount = 0;
            foreach ($errorRecords as $record) {
                $output->writeln(sprintf('%-10s %-15s %-20s %-15s %-20s %-15s %-30s %-20s', 
                           $record['id'], 
                           $record['user_id'], 
                           $record['phone'], 
                           $record['project_id'], 
                           $record['project_name'], 
                           $record['change_balance'], 
                           $record['remark'],
                           $record['created_at']));
                $totalAmount += $record['change_balance'];
            }
            $output->writeln(str_repeat('-', 140));
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
                    $output->writeln('<comment>恢复用户ID: ' . $record['user_id'] . ', 手机号: ' . $record['phone'] . ', 金额: ' . $record['change_balance'] . ', 项目ID: ' . $record['project_id'] . ', 备注: ' . $record['remark'] . '</comment>');
                    
                    // 验证用户是否存在
                    $user = User::where('id', $record['user_id'])->find();
                    if (!$user) {
                        throw new \Exception("用户不存在");
                    }
                    
                    // 开始事务
                    Db::startTrans();
                    
                    // 根据不同的remark类型进行不同的恢复操作
                    if ($record['remark'] == '购买商品到期分红-修复-增加puhui') {
                        // 这是错误增加puhui的记录，需要减少puhui
                        if ($user['puhui'] < $record['change_balance']) {
                            throw new \Exception("puhui余额不足，当前余额: {$user['puhui']}, 需要扣除: {$record['change_balance']}");
                        }
                        
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
                        
                    } elseif ($record['remark'] == '购买商品到期分红-修复-减少digit_balance') {
                        // 这是错误减少digit_balance的记录，需要增加digit_balance
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
                    } else {
                        throw new \Exception("未知的备注类型: " . $record['remark']);
                    }
                    
                    // 修改原记录的remark
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

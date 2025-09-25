<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\User;
use app\model\UserBalanceLog;

/**
 * 正确发放购买商品到期分红到puhui钱包
 * 
 * 功能：
 * 1. 查询remark='购买商品到期分红'且项目ID=70的记录
 * 2. 扣除digit_balance钱包中多发的金额
 * 3. 将这些金额正确发放到puhui钱包
 * 4. 修改原记录的remark为'购买商品到期分红-已正确发放'
 */
class CorrectBalanceLog extends Command
{
    protected function configure()
    {
        $this->setName('correct:balance-log')
            ->setDescription('扣除多发digit_balance并正确发放到puhui钱包')
            ->addOption('dry-run', null, \think\console\input\Option::VALUE_NONE, '仅查看需要发放的记录，不执行发放操作')
            ->addOption('confirm', null, \think\console\input\Option::VALUE_NONE, '确认执行发放操作');
    }

    protected function execute(Input $input, Output $output)
    {
        $isDryRun = $input->getOption('dry-run');
        $isConfirmed = $input->getOption('confirm');

        try {
            if ($isDryRun) {
                $output->writeln('<info>=== 预览模式：仅查看需要处理的记录 ===</info>');
            } else {
                $output->writeln('<info>=== 开始扣除多发digit_balance并正确发放到puhui钱包 ===</info>');
            }
            
            // 查询需要正确发放的记录
            $sql = "SELECT ub.*, u.phone
                    FROM mp_user_balance_log as ub 
                    LEFT JOIN mp_user AS u ON(u.id = ub.user_id)
                    WHERE ub.type=59 AND ub.log_type=5 AND ub.remark='购买商品到期分红-修复' 
                    AND ub.created_at > '2025-09-01 00:00:06'";
            
            $allRecords = Db::query($sql);
            
            if (empty($allRecords)) {
                $output->writeln('<info>没有找到需要发放的记录</info>');
                return;
            }
            
            $output->writeln('<info>找到 ' . count($allRecords) . ' 条记录，正在筛选项目ID=70的订单...</info>');
            
            // 筛选出项目ID为70的订单
            $correctRecords = [];
            foreach ($allRecords as $record) {
                // 通过relation_id查询对应的订单，检查project_id是否为70
                $order = Db::name('order')->where('id', $record['relation_id'])->find();
                if ($order && $order['project_id'] == 70) {
                    $record['project_name'] = $order['project_name'];
                    $record['project_id'] = $order['project_id'];
                    $correctRecords[] = $record;
                }
            }
            
            if (empty($correctRecords)) {
                $output->writeln('<info>没有找到项目ID=70的购买商品到期分红记录</info>');
                return;
            }
            
            $output->writeln('<info>找到 ' . count($correctRecords) . ' 条需要处理的记录</info>');
            
            // 显示记录详情
            $output->writeln('');
            $output->writeln('<comment>需要处理的记录详情：</comment>');
            $output->writeln(str_repeat('-', 120));
            $output->writeln(sprintf('%-10s %-15s %-20s %-15s %-20s %-15s %-15s', 
                       'ID', '用户ID', '手机号', '项目ID', '项目名称', '金额', '创建时间'));
            $output->writeln(str_repeat('-', 120));
            
            $totalAmount = 0;
            foreach ($correctRecords as $record) {
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
                $output->writeln('<info>预览完成。如需执行处理，请使用 --confirm 参数。</info>');
                return;
            }
            
            if (!$isConfirmed) {
                $output->writeln('<error>警告：此操作将修改用户余额数据！</error>');
                $output->writeln('<error>请确认后使用 --confirm 参数执行处理操作。</error>');
                return;
            }
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($correctRecords as $record) {
                try {
                    $output->writeln('<comment>处理用户ID: ' . $record['user_id'] . ', 手机号: ' . $record['phone'] . ', 金额: ' . $record['change_balance'] . '</comment>');
                    
                    // 验证用户是否存在
                    $user = User::where('id', $record['user_id'])->find();
                    if (!$user) {
                        throw new \Exception("用户不存在");
                    }
                    
                    // 验证digit_balance余额是否足够扣除
                    if ($user['digit_balance'] < $record['change_balance']) {
                        throw new \Exception("digit_balance余额不足，当前余额: {$user['digit_balance']}, 需要扣除: {$record['change_balance']}");
                    }
                    
                    // 开始事务
                    Db::startTrans();
                    
                    // 1. 扣除digit_balance钱包中多发的金额
                    User::changeInc(
                        $record['user_id'], 
                        -$record['change_balance'],  // 负数表示减少
                        'digit_balance', 
                        59,  // type
                        $record['relation_id'], 
                        5,   // log_type (digit_balance钱包对应log_type=5)
                        '同舟筑福收益转出', 
                        0,   // admin_user_id
                        2,   // status
                        'XF' // sn_prefix
                    );
                    
                    // 2. 发放到puhui钱包
                    User::changeInc(
                        $record['user_id'], 
                        $record['change_balance'],   // 正数表示增加
                        'puhui', 
                        59,  // type
                        $record['relation_id'], 
                        13,  // log_type (puhui钱包对应log_type=13)
                        '福泽普惠专享', 
                        0,   // admin_user_id
                        2,   // status
                        'XF' // sn_prefix
                    );
                    
                    // 修改原记录的remark
                    UserBalanceLog::where('id', $record['id'])
                        ->update(['remark' => '购买商品到期分红-正确发放']);
                    
                    // 提交事务
                    Db::commit();
                    
                    $successCount++;
                    $output->writeln('<info>✓ 处理成功</info>');
                    
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    
                    $errorCount++;
                    $output->writeln('<error>✗ 处理失败: ' . $e->getMessage() . '</error>');
                }
                
                $output->writeln('---');
            }
            
            $output->writeln('');
            $output->writeln('<info>处理完成！</info>');
            $output->writeln('<info>成功: ' . $successCount . ' 条</info>');
            $output->writeln('<error>失败: ' . $errorCount . ' 条</error>');
            
        } catch (\Exception $e) {
            $output->writeln('<error>脚本执行出错: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>错误文件: ' . $e->getFile() . '</error>');
            $output->writeln('<error>错误行号: ' . $e->getLine() . '</error>');
        }
    }
}

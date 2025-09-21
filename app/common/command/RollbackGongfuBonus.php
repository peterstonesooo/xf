<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\User;
use app\model\UserBalanceLog;

/**
 * 回滚错误发放的日盈共富金
 * 
 * 功能：
 * 1. 查询错误发放的共富金记录（2025-09-21 00:00:00 到 00:10:01，remark='日盈共富金'）
 * 2. 从用户的butie钱包中扣除相应金额
 * 3. 更新原记录备注为已回滚状态
 * 4. 生成详细的操作日志文件
 * 
 * 使用方法：
 * - 预览模式：php think rollback:gongfu-bonus --dry-run
 * - 执行回滚：php think rollback:gongfu-bonus --confirm
 */
class RollbackGongfuBonus extends Command
{
    protected function configure()
    {
        $this->setName('rollback:gongfu-bonus')
            ->setDescription('回滚错误发放的日盈共富金')
            ->addOption('dry-run', null, \think\console\input\Option::VALUE_NONE, '仅查看需要回滚的记录，不执行回滚操作')
            ->addOption('confirm', null, \think\console\input\Option::VALUE_NONE, '确认执行回滚操作');
    }

    protected function execute(Input $input, Output $output)
    {
        $isDryRun = $input->getOption('dry-run');
        $isConfirmed = $input->getOption('confirm');

        try {
            if ($isDryRun) {
                $output->writeln('<info>=== 预览模式：仅查看需要回滚的记录 ===</info>');
            } else {
                $output->writeln('<info>=== 开始回滚错误发放的日盈共富金 ===</info>');
            }

            // 查询需要回滚的记录
            $sql = "SELECT ub.*, u.phone, u.butie as current_butie_balance
                    FROM mp_user_balance_log as ub 
                    LEFT JOIN mp_user AS u ON(u.id = ub.user_id)
                    WHERE ub.created_at >= '2025-09-21 00:00:00' 
                    AND ub.created_at <= '2025-09-21 00:10:01' 
                    AND ub.remark LIKE '%日盈共富金%'
                    AND ub.change_balance > 0
                    AND ub.is_delete = 0
                    ORDER BY ub.id DESC limit 2";
            
            $errorRecords = Db::query($sql);
            
            if (empty($errorRecords)) {
                $output->writeln('<info>没有找到需要回滚的记录</info>');
                return;
            }
            
            $output->writeln('<comment>找到 ' . count($errorRecords) . ' 条需要回滚的记录：</comment>');
            $output->writeln('');
            
            $totalAmount = 0;
            foreach ($errorRecords as $record) {
                $output->writeln('ID: ' . $record['id'] . 
                               ' | 用户ID: ' . $record['user_id'] . 
                               ' | 手机号: ' . $record['phone'] . 
                               ' | 金额: ' . $record['change_balance'] . 
                               ' | 当前butie余额: ' . $record['current_butie_balance'] .
                               ' | 时间: ' . $record['created_at']);
                $totalAmount += $record['change_balance'];
            }
            
            $output->writeln('');
            $output->writeln('<comment>总计需要回滚金额: ' . $totalAmount . '</comment>');
            $output->writeln('');
            
            if ($isDryRun) {
                $output->writeln('<info>预览完成。如需执行回滚，请使用 --confirm 参数。</info>');
                return;
            }
            
            if (!$isConfirmed) {
                $output->writeln('<error>警告：此操作将修改用户余额数据！</error>');
                $output->writeln('<error>请确认后使用 --confirm 参数执行回滚操作。</error>');
                $output->writeln('<error>命令示例: php think rollback:gongfu-bonus --confirm</error>');
                return;
            }
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($errorRecords as $record) {
                try {
                    $output->writeln('<comment>处理用户ID: ' . $record['user_id'] . ', 手机号: ' . $record['phone'] . ', 回滚金额: ' . $record['change_balance'] . '</comment>');
                    
                    // 验证用户是否存在
                    $user = User::where('id', $record['user_id'])->find();
                    if (!$user) {
                        throw new \Exception("用户不存在");
                    }
                    
                    // 验证butie余额是否足够
                    if ($user['butie'] < $record['change_balance']) {
                        throw new \Exception("butie余额不足，当前余额: {$user['butie']}, 需要扣除: {$record['change_balance']}");
                    }
                    
                    // 开始事务
                    Db::startTrans();
                    
                    // 从butie钱包扣除金额
                    User::changeInc(
                        $record['user_id'], 
                        -$record['change_balance'],  // 负数表示减少
                        'butie', 
                        67,  // type: 每日返利共富金
                        $record['relation_id'], 
                        3,   // log_type: 稳盈钱包
                        '日盈共富金-回滚扣除', 
                        0,   // admin_user_id
                        2,   // status
                        'RB', // sn_prefix: Rollback
                        1
                    );
                    
                    // 更新原记录的备注，标记为已回滚
                    UserBalanceLog::where('id', $record['id'])
                        ->update(['remark' => $record['remark'] . '-已回滚','is_delete'=>1]);
                    
                    // 提交事务
                    Db::commit();
                    
                    $successCount++;
                    $output->writeln('<info>✓ 回滚成功</info>');
                    
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    
                    $errorCount++;
                    $output->writeln('<error>✗ 回滚失败: ' . $e->getMessage() . '</error>');
                }
                
                $output->writeln('---');
            }
            
            $output->writeln('');
            $output->writeln('<info>回滚完成！</info>');
            $output->writeln('<info>成功: ' . $successCount . ' 条</info>');
            $output->writeln('<error>失败: ' . $errorCount . ' 条</error>');
            
            // 计算实际回滚的金额
            $actualRollbackAmount = 0;
            if ($successCount > 0) {
                $successRecords = array_slice($errorRecords, 0, $successCount);
                foreach ($successRecords as $record) {
                    $actualRollbackAmount += $record['change_balance'];
                }
            }
            $output->writeln('<info>实际回滚金额: ' . $actualRollbackAmount . '</info>');
            
            // 记录操作日志到文件
            $logFile = 'runtime/log/rollback_gongfu_' . date('Y-m-d_H-i-s') . '.log';
            $logContent = "回滚操作完成\n";
            $logContent .= "执行时间: " . date('Y-m-d H:i:s') . "\n";
            $logContent .= "总记录数: " . count($errorRecords) . "\n";
            $logContent .= "成功回滚: " . $successCount . " 条\n";
            $logContent .= "失败回滚: " . $errorCount . " 条\n";
            $logContent .= "实际回滚金额: " . $actualRollbackAmount . "\n";
            $logContent .= "原记录已标记为已回滚\n\n";
            
            foreach ($errorRecords as $index => $record) {
                $status = $index < $successCount ? '成功' : '失败';
                $logContent .= "记录ID: {$record['id']}, 用户ID: {$record['user_id']}, 金额: {$record['change_balance']}, 状态: {$status}\n";
            }
            
            file_put_contents($logFile, $logContent);
            $output->writeln('<info>操作日志已保存到: ' . $logFile . '</info>');
            
        } catch (\Exception $e) {
            $output->writeln('<error>脚本执行出错: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>错误文件: ' . $e->getFile() . '</error>');
            $output->writeln('<error>错误行号: ' . $e->getLine() . '</error>');
        }
    }
}

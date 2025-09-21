<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\TeamRewardRecord;

/**
 * 回滚错误发放的幸福权益团队奖励
 * 
 * 功能：
 * 1. 查询错误发放的幸福权益团队奖励记录（remark LIKE '%幸福权益团队奖励-阶段%'）
 * 2. 从用户的钱包中扣除相应金额（普惠钱包、助力券、荣誉金）
 * 3. 更新原记录备注为已回滚状态
 * 4. 删除对应的团队奖励记录
 * 5. 生成详细的操作日志文件
 * 
 * 使用方法：
 * - 预览模式：php think rollback:happiness-team-reward --dry-run
 * - 执行回滚：php think rollback:happiness-team-reward --confirm
 */
class RollbackHappinessTeamReward extends Command
{
    protected function configure()
    {
        $this->setName('rollback:happiness-team-reward')
            ->setDescription('回滚错误发放的幸福权益团队奖励')
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
                $output->writeln('<info>=== 开始回滚错误发放的幸福权益团队奖励 ===</info>');
            }

            // 查询需要回滚的记录
            $sql = "SELECT ub.*, u.phone, 
                           u.puhui as current_puhui_balance,
                           u.xingfu_tickets as current_tickets_balance,
                           u.team_bonus_balance as current_honor_balance
                    FROM mp_user_balance_log as ub 
                    LEFT JOIN mp_user AS u ON(u.id = ub.user_id)
                    WHERE ub.remark LIKE '%幸福权益团队奖励-阶段%'
                    AND ub.change_balance > 0
                    AND ub.log_type IN (2, 12, 13)
                    and ub.is_delete = 0
                    ORDER BY ub.id DESC limit 2";
            
            $errorRecords = Db::query($sql);
            
            if (empty($errorRecords)) {
                $output->writeln('<info>没有找到需要回滚的记录</info>');
                return;
            }
            
            $output->writeln('<comment>找到 ' . count($errorRecords) . ' 条需要回滚的记录：</comment>');
            $output->writeln('');
            
            $totalPuhuiAmount = 0;
            $totalTicketsAmount = 0;
            $totalHonorAmount = 0;
            
            foreach ($errorRecords as $record) {
                $walletType = $this->getWalletTypeFromLogType($record['log_type']);
                $walletName = $this->getWalletName($walletType);
                $currentBalance = $this->getCurrentBalance($record, $walletType);
                
                $output->writeln('ID: ' . $record['id'] . 
                               ' | 用户ID: ' . $record['user_id'] . 
                               ' | 手机号: ' . $record['phone'] . 
                               ' | LogType: ' . $record['log_type'] .
                               ' | 钱包类型: ' . $walletName .
                               ' | 金额: ' . $record['change_balance'] . 
                               ' | 当前余额: ' . $currentBalance .
                               ' | 时间: ' . $record['created_at']);
                
                // 累计各种钱包的回滚金额
                switch ($walletType) {
                    case 'puhui':
                        $totalPuhuiAmount += $record['change_balance'];
                        break;
                    case 'xingfu_tickets':
                        $totalTicketsAmount += $record['change_balance'];
                        break;
                    case 'team_bonus_balance':
                        $totalHonorAmount += $record['change_balance'];
                        break;
                }
            }
            
            $output->writeln('');
            $output->writeln('<comment>回滚统计：</comment>');
            $output->writeln('<comment>普惠钱包总计: ' . $totalPuhuiAmount . '</comment>');
            $output->writeln('<comment>助力券总计: ' . $totalTicketsAmount . '</comment>');
            $output->writeln('<comment>荣誉金总计: ' . $totalHonorAmount . '</comment>');
            $output->writeln('<comment>总计回滚金额: ' . ($totalPuhuiAmount + $totalTicketsAmount + $totalHonorAmount) . '</comment>');
            $output->writeln('');
            
            if ($isDryRun) {
                $output->writeln('<info>预览完成。如需执行回滚，请使用 --confirm 参数。</info>');
                return;
            }
            
            if (!$isConfirmed) {
                $output->writeln('<error>警告：此操作将修改用户余额数据！</error>');
                $output->writeln('<error>请确认后使用 --confirm 参数执行回滚操作。</error>');
                $output->writeln('<error>命令示例: php think rollback:happiness-team-reward --confirm</error>');
                return;
            }
            
            $successCount = 0;
            $errorCount = 0;
            $rollbackDetails = [];
            
            foreach ($errorRecords as $record) {
                try {
                    $walletType = $this->getWalletTypeFromLogType($record['log_type']);
                    $walletName = $this->getWalletName($walletType);
                    
                    $output->writeln('<comment>处理用户ID: ' . $record['user_id'] . ', 手机号: ' . $record['phone'] . ', LogType: ' . $record['log_type'] . ', 钱包: ' . $walletName . ', 回滚金额: ' . $record['change_balance'] . '</comment>');
                    
                    // 验证用户是否存在
                    $user = User::where('id', $record['user_id'])->find();
                    if (!$user) {
                        throw new \Exception("用户不存在");
                    }
                    
                    // 验证钱包余额是否足够
                    $currentBalance = $this->getCurrentBalance($user, $walletType);
                    if ($currentBalance < $record['change_balance']) {
                        throw new \Exception("{$walletName}余额不足，当前余额: {$currentBalance}, 需要扣除: {$record['change_balance']}");
                    }
                    
                    // 开始事务
                    Db::startTrans();
                    
                    // 从对应钱包扣除金额
                    User::changeInc(
                        $record['user_id'], 
                        -$record['change_balance'],  // 负数表示减少
                        $walletType, 
                        118, // type: 幸福权益团队奖励
                        $record['relation_id'], 
                        $record['log_type'], // 使用原记录的log_type
                        '幸福权益团队奖励-回滚扣除', 
                        0,   // admin_user_id
                        2,   // status
                        'RB', // sn_prefix: Rollback
                        1
                    );
                    
                    // 更新原记录的备注，标记为已回滚
                    UserBalanceLog::where('id', $record['id'])
                        ->update(['remark' => $record['remark'] . '-已回滚','is_delete'=>1]);
                    
                    // 删除对应的团队奖励记录
                    $this->deleteTeamRewardRecord($record);
                    
                    // 提交事务
                    Db::commit();
                    
                    $successCount++;
                    $rollbackDetails[] = [
                        'user_id' => $record['user_id'],
                        'phone' => $record['phone'],
                        'wallet_type' => $walletName,
                        'amount' => $record['change_balance'],
                        'status' => 'success'
                    ];
                    $output->writeln('<info>✓ 回滚成功</info>');
                    
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    
                    $errorCount++;
                    $rollbackDetails[] = [
                        'user_id' => $record['user_id'],
                        'phone' => $record['phone'],
                        'wallet_type' => $walletName ?? 'unknown',
                        'amount' => $record['change_balance'],
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                    $output->writeln('<error>✗ 回滚失败: ' . $e->getMessage() . '</error>');
                }
                
                $output->writeln('---');
            }
            
            $output->writeln('');
            $output->writeln('<info>回滚完成！</info>');
            $output->writeln('<info>成功: ' . $successCount . ' 条</info>');
            $output->writeln('<error>失败: ' . $errorCount . ' 条</error>');
            
            // 计算实际回滚的金额
            $actualPuhuiAmount = 0;
            $actualTicketsAmount = 0;
            $actualHonorAmount = 0;
            
            foreach ($rollbackDetails as $detail) {
                if ($detail['status'] === 'success') {
                    switch ($detail['wallet_type']) {
                        case '普惠钱包':
                            $actualPuhuiAmount += $detail['amount'];
                            break;
                        case '助力券':
                            $actualTicketsAmount += $detail['amount'];
                            break;
                        case '荣誉金':
                            $actualHonorAmount += $detail['amount'];
                            break;
                    }
                }
            }
            
            $output->writeln('<info>实际回滚统计：</info>');
            $output->writeln('<info>普惠钱包: ' . $actualPuhuiAmount . '</info>');
            $output->writeln('<info>助力券: ' . $actualTicketsAmount . '</info>');
            $output->writeln('<info>荣誉金: ' . $actualHonorAmount . '</info>');
            $output->writeln('<info>总计: ' . ($actualPuhuiAmount + $actualTicketsAmount + $actualHonorAmount) . '</info>');
            
            // 记录操作日志到文件
            $this->saveOperationLog($rollbackDetails, $successCount, $errorCount, $actualPuhuiAmount, $actualTicketsAmount, $actualHonorAmount);
            $output->writeln('<info>操作日志已保存到: runtime/log/rollback_happiness_team_' . date('Y-m-d_H-i-s') . '.log</info>');
            
        } catch (\Exception $e) {
            $output->writeln('<error>脚本执行出错: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>错误文件: ' . $e->getFile() . '</error>');
            $output->writeln('<error>错误行号: ' . $e->getLine() . '</error>');
        }
    }
    
    /**
     * 从log_type获取钱包类型
     */
    private function getWalletTypeFromLogType($logType)
    {
        switch ($logType) {
            case 2:
                return 'team_bonus_balance'; // 团队奖励钱包
            case 12:
                return 'xingfu_tickets';     // 助力券
            case 13:
                return 'puhui';             // 普惠钱包
            default:
                return 'puhui'; // 默认
        }
    }
    
    /**
     * 获取钱包名称
     */
    private function getWalletName($walletType)
    {
        $names = [
            'puhui' => '普惠钱包',
            'xingfu_tickets' => '助力券',
            'team_bonus_balance' => '荣誉金'
        ];
        return $names[$walletType] ?? '未知钱包';
    }
    
    /**
     * 获取当前余额
     */
    private function getCurrentBalance($record, $walletType)
    {
        switch ($walletType) {
            case 'puhui':
                return $record['current_puhui_balance'] ?? $record['puhui'] ?? 0;
            case 'xingfu_tickets':
                return $record['current_tickets_balance'] ?? $record['xingfu_tickets'] ?? 0;
            case 'team_bonus_balance':
                return $record['current_honor_balance'] ?? $record['team_bonus_balance'] ?? 0;
            default:
                return 0;
        }
    }
    
    
    /**
     * 删除对应的团队奖励记录
     */
    private function deleteTeamRewardRecord($record)
    {
        // 根据用户ID和备注信息查找对应的团队奖励记录
        $remark = $record['remark'];
        
        // 提取阶段信息
        if (preg_match('/幸福权益团队奖励-(阶段\d+)/', $remark, $matches)) {
            $stageName = $matches[1];
            $stage = intval(str_replace('阶段', '', $stageName));
            
            // 删除团队奖励记录
            TeamRewardRecord::where('sub_user_id', $record['user_id'])
                ->where('reward_level', $stage)
                ->where('reward_type', 'like', '%幸福权益团队奖励%')
                ->delete();
        }
    }
    
    /**
     * 保存操作日志
     */
    private function saveOperationLog($rollbackDetails, $successCount, $errorCount, $actualPuhuiAmount, $actualTicketsAmount, $actualHonorAmount)
    {
        $logFile = 'runtime/log/rollback_happiness_team_' . date('Y-m-d_H-i-s') . '.log';
        $logContent = "幸福权益团队奖励回滚操作完成\n";
        $logContent .= "执行时间: " . date('Y-m-d H:i:s') . "\n";
        $logContent .= "总记录数: " . count($rollbackDetails) . "\n";
        $logContent .= "成功回滚: " . $successCount . " 条\n";
        $logContent .= "失败回滚: " . $errorCount . " 条\n";
        $logContent .= "实际回滚统计:\n";
        $logContent .= "- 普惠钱包: " . $actualPuhuiAmount . "\n";
        $logContent .= "- 助力券: " . $actualTicketsAmount . "\n";
        $logContent .= "- 荣誉金: " . $actualHonorAmount . "\n";
        $logContent .= "- 总计: " . ($actualPuhuiAmount + $actualTicketsAmount + $actualHonorAmount) . "\n";
        $logContent .= "原记录已标记为已回滚，团队奖励记录已删除\n\n";
        
        foreach ($rollbackDetails as $detail) {
            $logContent .= "用户ID: {$detail['user_id']}, 手机号: {$detail['phone']}, 钱包: {$detail['wallet_type']}, 金额: {$detail['amount']}, 状态: {$detail['status']}";
            if (isset($detail['error'])) {
                $logContent .= ", 错误: {$detail['error']}";
            }
            $logContent .= "\n";
        }
        
        file_put_contents($logFile, $logContent);
    }
}

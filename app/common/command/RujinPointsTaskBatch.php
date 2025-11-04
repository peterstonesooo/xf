<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use app\model\User;
use app\model\UserBalanceLog;
use think\facade\Log;
use think\facade\Db;
use Exception;

class RujinPointsTaskBatch extends Command
{
    /**
     * 批量处理优化版本
     * 配置命令：nohup php think RujinPointsTaskBatch >> /www/wwwroot/xf/runtime/log/points-task-batch.log 2>&1 &
     */
    protected function configure()
    {
        $this->setName('RujinPointsTaskBatch')
            ->setDescription('批量入金任务队列 - 批量优化版本');
    }

    protected function execute(Input $input, Output $output)
    {
        $redis = Cache::store('redis')->handler();
        $queueName = '批量入金任务队列';
        $batchSize = 100; // 每次批量处理的数量
        
        $output->writeln("批量入金任务启动成功，开始监听队列...\n");
        
        while (true) {
            try {
                $batch = [];
                $startTime = microtime(true);
                
                // 检查队列长度
                $queueLength = $redis->lLen($queueName);
                $output->writeln("[" . date('Y-m-d H:i:s') . "] 当前队列长度: {$queueLength}");
                
                // 批量获取数据（非阻塞方式）
                for ($i = 0; $i < $batchSize; $i++) {
                    $data = $redis->rPop($queueName);
                    if (!$data) {
                        break;
                    }
                    $batch[] = $data;
                }
                
                // 如果没有数据，等待一下
                if (empty($batch)) {
                    $output->writeln("队列为空，等待新数据...\n");
                    sleep(2);
                    continue;
                }
                
                $output->writeln("获取到 " . count($batch) . " 条数据，开始批量处理...");
                
                // 批量处理
                $this->processBatch($batch, $output);
                
                $endTime = microtime(true);
                $output->writeln("批量处理完成，耗时: " . round($endTime - $startTime, 2) . " 秒\n");
                
            } catch (Exception $e) {
                $output->writeln("系统错误: {$e->getMessage()}");
                $output->writeln("错误堆栈: " . $e->getTraceAsString());
                sleep(3);
            }
        }
    }
    
    /**
     * 批量处理数据
     */
    protected function processBatch($batch, $output)
    {
        // 解析所有数据
        $tasks = [];
        foreach ($batch as $queueValue) {
            $params = [];
            parse_str(str_replace('｜', '&', $queueValue), $params);
            
            $phone = str_replace('用户phone=', '', $params['用户phone'] ?? '');
            $amount = str_replace('金额=', '', $params['金额'] ?? '0');
            $walletType = str_replace('钱包=', '', $params['钱包'] ?? 'points');
            $batchId = str_replace('批次id=', '', $params['批次id'] ?? '');
            $remark = str_replace('备注=', '', $params['备注'] ?? '');
            
            if (empty($phone) || empty($amount)) {
                $output->writeln("无效的数据: {$queueValue}");
                continue;
            }
            
            $tasks[] = [
                'phone' => trim($phone),
                'amount' => $amount,
                'wallet_type' => $walletType,
                'batch_id' => $batchId,
                'remark' => $remark,
                'raw_data' => $queueValue
            ];
        }
        
        if (empty($tasks)) {
            return;
        }
        
        // 批量查询所有用户
        $phones = array_column($tasks, 'phone');
        $userList = User::whereIn('phone', $phones)->select();
        
        // 将用户列表转换为以phone为键的数组
        $users = [];
        foreach ($userList as $user) {
            $users[$user['phone']] = $user;
        }
        
        // 按钱包类型分组处理
        $groupedTasks = [];
        $failedTasks = [];
        
        foreach ($tasks as $task) {
            $phone = $task['phone'];
            
            if (!isset($users[$phone])) {
                $output->writeln("用户不存在: {$phone}");
                $this->recordFailure($task['batch_id'], $phone);
                $failedTasks[] = $task;
                continue;
            }
            
            $user = $users[$phone];
            $task['user'] = $user;
            
            // 根据钱包类型分组
            $groupedTasks[$task['wallet_type']][] = $task;
        }
        
        // 按钱包类型批量处理
        foreach ($groupedTasks as $walletType => $walletTasks) {
            $this->processByWalletType($walletType, $walletTasks, $output);
        }
    }
    
    /**
     * 按钱包类型批量处理
     */
    protected function processByWalletType($walletType, $tasks, $output)
    {
        // 确定字段和日志类型
        $fieldMap = [
            'topup_balance' => ['field' => 'topup_balance', 'log_type' => 1],
            'team_bonus_balance' => ['field' => 'team_bonus_balance', 'log_type' => 2],
            'butie' => ['field' => 'butie', 'log_type' => 3],
            'balance' => ['field' => 'balance', 'log_type' => 4],
            'gongfu_wallet' => ['field' => 'gongfu_wallet', 'log_type' => 16],
            'puhui' => ['field' => 'puhui', 'log_type' => 13],
            'zhenxing_wallet' => ['field' => 'zhenxing_wallet', 'log_type' => 14],
        ];
        
        if (!isset($fieldMap[$walletType])) {
            $output->writeln("未知的钱包类型: {$walletType}");
            return;
        }
        
        $field = $fieldMap[$walletType]['field'];
        $log_type = $fieldMap[$walletType]['log_type'];
        
        // 按用户ID合并同一用户的多次操作，确保数据一致性
        $userAmounts = [];  // 记录每个用户的总金额
        $userTasks = [];    // 记录每个用户的所有任务（用于日志）
        
        foreach ($tasks as $task) {
            $userId = $task['user']['id'];
            
            if (!isset($userAmounts[$userId])) {
                $userAmounts[$userId] = 0;
                $userTasks[$userId] = [];
            }
            
            $userAmounts[$userId] += $task['amount'];
            $userTasks[$userId][] = $task;
        }
        
        Db::startTrans();
        try {
            $updateCases = [];
            $userIds = [];
            $allBalanceLogs = [];
            
            // 按用户处理，确保每个用户只更新一次
            foreach ($userAmounts as $userId => $totalAmount) {
                $userIds[] = $userId;
                
                // 构建批量更新的 CASE 语句（每个用户只有一个WHEN）
                $updateCases[] = "WHEN {$userId} THEN {$field} + {$totalAmount}";
            }
            
            // 批量更新用户余额
            if (!empty($userIds)) {
                $userIdsStr = implode(',', $userIds);
                
                // 【重要】先加行级锁，防止其他进程同时修改同一用户的余额
                // FOR UPDATE 会锁定这些用户的行，其他事务需要等待当前事务完成
                Db::execute("SELECT id, {$field} FROM mp_user WHERE id IN ({$userIdsStr}) FOR UPDATE");
                
                $caseSql = implode(' ', $updateCases);
                $sql = "UPDATE mp_user SET {$field} = CASE id {$caseSql} END WHERE id IN ({$userIdsStr})";
                Db::execute($sql);
            }
            
            // 更新后重新查询用户数据，获取准确的余额信息
            $updatedUsers = User::whereIn('id', $userIds)->select();
            $updatedUsersMap = [];
            foreach ($updatedUsers as $user) {
                $updatedUsersMap[$user['id']] = $user;
            }
            
            // 为每个任务生成日志记录（使用准确的余额）
            foreach ($userTasks as $userId => $tasksForUser) {
                $currentBalance = $updatedUsersMap[$userId][$field] ?? 0;
                
                // 从后往前计算每个操作的 before_balance
                $tasksCount = count($tasksForUser);
                for ($i = $tasksCount - 1; $i >= 0; $i--) {
                    $task = $tasksForUser[$i];
                    $amount = $task['amount'];
                    
                    $afterBalance = $currentBalance;
                    $beforeBalance = $currentBalance - $amount;
                    
                    $sn = build_order_sn($userId, 'MR');
                    $allBalanceLogs[] = [
                        'user_id' => $userId,
                        'type' => 102,
                        'log_type' => $log_type,
                        'relation_id' => 0,
                        'before_balance' => $beforeBalance,
                        'change_balance' => $amount,
                        'after_balance' => $afterBalance,
                        'remark' => $task['remark'],
                        'admin_user_id' => 0,
                        'status' => 1,
                        'order_sn' => $sn,
                        'is_delete' => 0,
                        'create_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                    ];
                    
                    $currentBalance = $beforeBalance;
                }
            }
            
            // 批量插入日志（分批插入，避免SQL太长）
            $chunkSize = 100;
            foreach (array_chunk($allBalanceLogs, $chunkSize) as $chunk) {
                Db::name('user_balance_log')->insertAll($chunk);
            }
            
            Db::commit();
            
            // 记录成功
            foreach ($tasks as $task) {
                $this->recordSuccess($task['batch_id'], $task['phone']);
                $output->writeln("处理成功: {$task['raw_data']}");
            }
            
        } catch (Exception $e) {
            Db::rollback();
            $output->writeln("批量处理失败: {$e->getMessage()}");
            
            // 记录失败
            foreach ($tasks as $task) {
                $this->recordFailure($task['batch_id'], $task['phone']);
                $output->writeln("处理失败: {$task['raw_data']}");
            }
        }
    }
    
    /**
     * 记录成功
     */
    protected function recordSuccess($batchId, $phone)
    {
        $key = '入金成功-' . $batchId;
        $value = Cache::store('redis')->get($key);
        if ($value) {
            Cache::store('redis')->set($key, $value . ',' . $phone);
        } else {
            Cache::store('redis')->set($key, $phone);
        }
    }
    
    /**
     * 记录失败
     */
    protected function recordFailure($batchId, $phone)
    {
        $key = '入金失败-' . $batchId;
        $value = Cache::store('redis')->get($key);
        if ($value) {
            Cache::store('redis')->set($key, $value . ',' . $phone);
        } else {
            Cache::store('redis')->set($key, $phone);
        }
    }
}


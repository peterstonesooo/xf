<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use app\model\User;
use think\facade\Log;
use think\facade\Db;
use Exception;

class RujinPointsTask extends Command
{
    /**
     * 配置命令
     * 建议使用 supervisor 来管理进程
     * 示例配置：
     * [program:points-task]
     * command=php think points:task
     * directory=/path/to/your/project
     * autostart=true
     * autorestart=true
     * user=www-data
     * numprocs=1
     * redirect_stderr=true
     * stdout_logfile=/path/to/your/project/runtime/points-task.log
     */
    protected function configure()
    {
        $this->setName('RujinPointsTask')
            ->setDescription('批量入金任务队列');
    }

    protected function execute(Input $input, Output $output)
    {
        $redis = Cache::store('redis')->handler();
        $queueName = '批量入金任务队列';
        
        while (true) {
            try {
                // 从队列中获取数据，设置超时时间为5秒
                $data = $redis->brPop($queueName, 5);
                
                if (!$data) {
                    // 如果没有数据，继续等待
                    continue;
                }
                
                // 解析队列数据
                $queueValue = $data[1];
                $params = [];
                parse_str(str_replace('｜', '&', $queueValue), $params);
                
                // 提取参数
                $phone = str_replace('用户phone=', '', $params['用户phone'] ?? '');
                $amount = str_replace('金额=', '', $params['金额'] ?? '0');
                $walletType = str_replace('钱包=', '', $params['钱包'] ?? 'points');
                $batchId = str_replace('批次id=', '', $params['批次id'] ?? '');
                $remark = str_replace('备注=', '', $params['备注'] ?? '');
                
                if (empty($phone) || empty($amount)) {
                    $output->writeln("无效的数据: {$queueValue}");
                    continue;
                }
                
                // 开启事务
                Db::startTrans();
                try {
                    // 查找用户，添加重试机制
                    $user = null;
                    $retryCount = 0;
                    $maxRetries = 2;
                    
                    while ($retryCount < $maxRetries) {
                        try {
                            $user = User::where('phone', trim($phone))->find();
                            if ($user) {
                                break; // 找到用户，跳出重试循环
                            }
                            $retryCount++;
                            if ($retryCount < $maxRetries) {
                                usleep(100000); // 等待1秒后重试
                            }
                        } catch (Exception $e) {
                            $retryCount++;
                            if ($retryCount < $maxRetries) {
                                usleep(100000); // 等待1秒后重试
                            } else {
                                throw $e; // 重试次数用完，抛出异常
                            }
                        }
                    }
                    
                    if (!$user) {
                        $output->writeln("处理失败: {$queueValue}, 错误: {$e->getMessage()}");
                        //根据批次id记录入金失败
                        if(Cache::store('redis')->get('入金失败-'.$batchId)){
                            $errorArr = Cache::store('redis')->get('入金失败-'.$batchId);
                            $errorArr = $errorArr.','.$phone;
                            Cache::store('redis')->set('入金失败-'.$batchId, $errorArr);
                        }else{
                            Cache::store('redis')->set('入金失败-'.$batchId, $phone);
                        }
                        throw new Exception("用户不存在: {$phone}");
                    }
                    
                    // 根据钱包类型更新用户余额
                    switch ($walletType) {
                        case 'topup_balance':
                            $field = 'topup_balance';
                            $log_type = 1;
                            break;
                        case 'team_bonus_balance':
                            $field = 'team_bonus_balance';
                            $log_type = 2;
                            break;
                        case 'butie':
                            $field = 'butie';
                            $log_type = 3;
                            break;
                        case 'balance':
                            $field = 'balance';
                            $log_type = 4;
                            break;
                        case 'digit_balance':
                            $field = 'digit_balance';
                            $log_type = 5;
                            break;
                        default:
                            throw new Exception("未知的钱包类型: {$walletType}");
                    }
                    User::changeInc($user->id, $amount, $field, 102, 0, $log_type, $remark);
                    
                    Db::commit();
                    //根据批次id记录入金成功
                    if(Cache::store('redis')->get('入金成功-'.$batchId)){
                        $successArr = Cache::store('redis')->get('入金成功-'.$batchId);
                        $successArr = $successArr.','.$phone;
                        Cache::store('redis')->set('入金成功-'.$batchId, $successArr);
                    }else{
                        Cache::store('redis')->set('入金成功-'.$batchId, $phone);
                    }

                    $output->writeln("处理成功: {$queueValue}");

                    
                } catch (Exception $e) {
                    Db::rollback();
                    $output->writeln("处理失败: {$queueValue}, 错误: {$e->getMessage()}");
                    //根据批次id记录入金失败
                    if(Cache::store('redis')->get('入金失败-'.$batchId)){
                        $errorArr = Cache::store('redis')->get('入金失败-'.$batchId);
                        $errorArr = $errorArr.','.$phone;
                        Cache::store('redis')->set('入金失败-'.$batchId, $errorArr);
                    }else{
                        Cache::store('redis')->set('入金失败-'.$batchId, $phone);
                    }
                }
                
            } catch (Exception $e) {
                $output->writeln("系统错误: {$e->getMessage()}");
                // 发生错误时暂停1秒
                sleep(3);
            }
        }
    }
} 
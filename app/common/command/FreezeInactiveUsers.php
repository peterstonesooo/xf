<?php

namespace app\common\command;

use app\model\User;
use app\model\UserSignin;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class FreezeInactiveUsers extends Command
{
    protected function configure()
    {
        $this->setName('freezeInactiveUsers')
            ->setDescription('冻结近7天没有签到的用户');
    }

    protected function execute(Input $input, Output $output)
    { 
        try {
            $successCount = 0;
            $failCount = 0;
            
            $days = dbconfig('freeze_inactive_users');
            // 计算N天前的日期
            $daysAgo = date('Y-m-d', strtotime('-' . $days . ' days'));
            // 计算N天前的时间戳（用于比较用户注册时间）
            $daysAgoTimestamp = strtotime('-' . $days . ' days');
            
            $output->writeln('开始检查近' . $days . '天未签到的用户...');
            $output->writeln($days . '天前日期：' . $daysAgo);
            
            // 使用 chunk 方法分批处理数据，每次处理500条
            // 只处理注册时间超过配置天数的用户
            User::where('status', 1)
                ->where('created_at', '<', date('Y-m-d H:i:s', $daysAgoTimestamp))
                ->order('id', 'asc')
                ->chunk(500, function($users) use (&$successCount, &$failCount, $output, $daysAgo) {
                foreach ($users as $user) {
                    try {
                        // 查询该用户最近一次签到记录
                        $lastSignin = UserSignin::where('user_id', $user->id)
                            ->order('signin_date', 'desc')
                            ->value('signin_date');
                        
                        // 如果没有签到记录，或最后签到日期在N天前
                        if (empty($lastSignin) || $lastSignin < $daysAgo) {
                            Db::startTrans();
                            try {
                                // 冻结用户
                                $user->status = 0;
                                $user->save();
                                
                                Db::commit();
                                $successCount++;
                                
                                $lastSigninDate = empty($lastSignin) ? '从未签到' : $lastSignin;
                                $output->writeln('已冻结用户 ID: ' . $user->id . ', 手机号: ' . $user->phone . ', 最后签到: ' . $lastSigninDate);
                                Log::info('冻结未签到用户，用户ID：' . $user->id . '，手机号：' . $user->phone . '，最后签到：' . $lastSigninDate);
                            } catch (\Exception $e) {
                                Db::rollback();
                                $failCount++;
                                $errorMsg = '冻结用户失败，用户ID：' . $user->id . '，错误信息：' . $e->getMessage();
                                Log::error($errorMsg);
                                $output->writeln($errorMsg);
                            }
                        }
                    } catch (\Exception $e) {
                        $failCount++;
                        $errorMsg = '处理用户失败，用户ID：' . $user->id . '，错误信息：' . $e->getMessage();
                        Log::error($errorMsg);
                        $output->writeln($errorMsg);
                    }
                }
            });
            
            $output->writeln('执行完成，成功冻结：' . $successCount . '，失败：' . $failCount);
            Log::info('冻结未签到用户任务完成，成功：' . $successCount . '，失败：' . $failCount);

        } catch (\Exception $e) {
            $output->writeln('执行出错：' . $e->getMessage());
            Log::error('冻结未签到用户任务异常：' . $e->getMessage());
        }
    }
}


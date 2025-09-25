<?php

namespace app\common\command;

use app\model\User;
use app\model\UserActive;
use app\model\Order;
use app\model\OrderDailyBonus;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use Exception;

class MigrateUserActive extends Command
{
    protected function configure()
    {
        $this->setName('migrateUserActive')->setDescription('迁移用户激活数据到mp_user_active表');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始迁移用户激活数据...');
        
        $startTime = strtotime('2025-09-21 00:00:00');
        $successCount = 0;
        $failCount = 0;
        $skipCount = 0;

        try {
            // 使用 chunk 分批处理用户数据
            User::field('id')->chunk(500, function($users) use (&$successCount, &$failCount, &$skipCount, $startTime, $output) {
                foreach ($users as $user) {
                    try {
                        

                        // 查找用户最早的订单时间（从2025-09-21开始）
                        $earliestOrderTime = $this->getEarliestOrderTime($user['id'], $startTime);
                        
                        if ($earliestOrderTime) {

                            // 检查用户是否已经激活
                            $existingActive = UserActive::where('user_id', $user['id'])->find();
                            if ($existingActive) {
                                if($existingActive['active_time'] != $earliestOrderTime){
                                    UserActive::where('user_id', $user['id'])->update([
                                        'active_time' => $earliestOrderTime
                                    ]);
                                }
                            }else{
                                // 用户有购买记录，创建激活记录
                                UserActive::create([
                                    'user_id' => $user['id'],
                                    'is_active' => 1,
                                    'active_time' => $earliestOrderTime
                                ]);
                            }
                            $successCount++;
                            $output->writeln("用户 {$user['id']} 激活记录已创建，激活时间: " . date('Y-m-d H:i:s', $earliestOrderTime));
                        } else {
                            $skipCount++;
                        }
                        
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

    /**
     * 获取用户最早的订单时间
     */
    private function getEarliestOrderTime($userId, $startTime)
    {
        $earliestTime = null;
        
        // 查询 mp_order 表中的最早订单时间
        $orderTime = Order::where('user_id', $userId)
                         ->where('created_at', '>=', date('Y-m-d H:i:s', $startTime))
                         ->order('created_at', 'asc')
                         ->value('created_at');
        
        if ($orderTime) {
            $earliestTime = strtotime($orderTime);
        }
        
        // 查询 mp_order_daily_bonus 表中的最早订单时间
        $dailyBonusTime = OrderDailyBonus::where('user_id', $userId)
                                        ->where('created_at', '>=', date('Y-m-d H:i:s', $startTime))
                                        ->order('created_at', 'asc')
                                        ->value('created_at');
        
        if ($dailyBonusTime) {
            $dailyBonusTimestamp = strtotime($dailyBonusTime);
            if ($earliestTime === null || $dailyBonusTimestamp < $earliestTime) {
                $earliestTime = $dailyBonusTimestamp;
            }
        }
        
        return $earliestTime;
    }
}

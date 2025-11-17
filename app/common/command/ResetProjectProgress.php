<?php

namespace app\common\command;

use app\model\Order;
use app\model\OrderDailyBonus;
use app\model\Project;
use app\model\User;
use app\model\UserBalanceLog;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class ResetProjectProgress extends Command
{
    protected function configure()
    {
        $this->setName('project:reset-progress')
            ->setDescription('将所有启用的项目进度条重置为0%')
            ->addOption('sendonly', null, \think\console\input\Option::VALUE_NONE, '仅执行VIP用户赠送抽奖券操作');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $sendOnly = $input->getOption('sendonly');
        $updated = 0;
        $sentCount = 0;

        // 如果不是仅赠送模式，执行项目进度重置
        if (!$sendOnly) {
            Project::where('status', 1)->where('project_group_id','in',[7,8,9,10,11])->chunk(200, function ($projects) use (&$updated) {
                foreach ($projects as $project) {
                    $totalStock = (int)($project['total_stock'] ?? 0);
                    if ($totalStock <= 0) {
                        continue;
                    }

                    $desiredRemaining = $totalStock;

                    // 计算真实购买数量
                    if ($project['daily_bonus_ratio'] > 0) {
                        $orderCount = OrderDailyBonus::where('project_id', $project['id'])->count();
                    } else {
                        $orderCount = Order::where('project_id', $project['id'])->count();
                    }

                    $times = $project['total_stock'] - $desiredRemaining - $orderCount;
                    $startTime = time() - ($times * 120);
                    $targetCreatedAt = date('Y-m-d H:i:s', $startTime);

                    if ((int)$project['remaining_stock'] !== $desiredRemaining || $project['created_at'] !== $targetCreatedAt) {
                        Project::where('id', $project['id'])->update([
                            'remaining_stock' => $desiredRemaining,
                            'created_at' => $targetCreatedAt,
                        ]);
                        $updated++;
                    }
                }
            });
        }

        // VIP用户赠送抽奖券
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';
        
        User::where([
            ['vip_status', '=', 1],
        ])->chunk(500, function($users) use (&$sentCount, $todayStart, $todayEnd) {
            foreach ($users as $user) {
                // 先快速检查，避免不必要的数据库操作
                $logCount = UserBalanceLog::where('user_id', $user['id'])
                    ->where('log_type', 127)
                    ->where('remark', 'vip用户赠送抽奖卷')
                    ->where('created_at', '>=', $todayStart)
                    ->where('created_at', '<=', $todayEnd)
                    ->count();
                
                if ($logCount > 0) {
                    continue; // 今天已赠送，跳过
                }
                
                // 使用事务和锁机制防止并发重复赠送
                Db::startTrans();
                try {
                    // 在事务内使用锁查询，确保原子性
                    $log = UserBalanceLog::where('user_id', $user['id'])
                        ->where('log_type', 127)
                        ->where('remark', 'vip用户赠送抽奖卷')
                        ->where('created_at', '>=', $todayStart)
                        ->where('created_at', '<=', $todayEnd)
                        ->lock(true)  // SELECT ... FOR UPDATE，加行锁
                        ->find();
                    
                    if ($log) {
                        Db::rollback();
                        continue; // 今天已赠送，跳过
                    }
                    
                    // 赠送抽奖券
                    // 注意：changeInc内部也有事务，但ThinkPHP会合并到外层事务中
                    User::changeInc($user['id'], 1, 'lottery_tickets', 127, $user['id'], 9, 'vip用户赠送抽奖卷', 0, 1);
                    Db::commit();
                    $sentCount++;
                } catch (\Exception $e) {
                    Db::rollback();
                    // 如果出错（比如锁超时、重复等），跳过该用户继续处理下一个
                    continue;
                }
            }
        });

        // 根据模式输出不同的信息
        if ($sendOnly) {
            $output->writeln(sprintf('VIP用户抽奖券赠送完成，共赠送 %d 个用户。', $sentCount));
        } else {
            $output->writeln(sprintf('项目进度重置完成，共更新 %d 个项目，VIP用户抽奖券赠送 %d 个用户。', $updated, $sentCount));
        }
        return 0;
    }
}


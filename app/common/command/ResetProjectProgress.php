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
        User::where([
            ['vip_status', '=', 1],
        ])->chunk(500, function($users) use (&$sentCount, $today) {
            foreach ($users as $user) {
                // 检查今天是否已经赠送过
                $log = UserBalanceLog::where('user_id', $user['id'])
                    ->where('log_type', 127)
                    ->where('remark', 'vip用户赠送抽奖卷')
                    ->where('created_at', '>=', $today . ' 00:00:00')
                    ->where('created_at', '<=', $today . ' 23:59:59')
                    ->find();
                if ($log) {
                    continue; // 今天已赠送，跳过
                }
                // 赠送抽奖券
                User::changeInc($user['id'], 1, 'lottery_tickets', 127, $user['id'], 9, 'vip用户赠送抽奖卷', 0, 1);
                $sentCount++;
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


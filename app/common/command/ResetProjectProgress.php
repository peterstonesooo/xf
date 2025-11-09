<?php

namespace app\common\command;

use app\model\Order;
use app\model\OrderDailyBonus;
use app\model\Project;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class ResetProjectProgress extends Command
{
    protected function configure()
    {
        $this->setName('project:reset-progress')
            ->setDescription('将所有启用的项目进度条重置为0%');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $updated = 0;

        Project::where('status', 1)->chunk(200, function ($projects) use (&$updated) {
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

        $output->writeln(sprintf('项目进度重置完成，共更新 %d 个项目。', $updated));
        return 0;
    }
}


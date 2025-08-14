<?php

namespace app\common\command;

use app\model\LevelConfig;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;

class SendCashReward extends Command
{
    protected function configure()
    {
        $this->setName('sendCashReward')->setDescription('发放数字生活补贴定时任务，每天的0点执行');
    }

    protected function execute(Input $input, Output $output)
    {
        $key = 'send-cash-reward-'.date('Ymd');
        if (!Cache::has($key)) {
            Cache::set($key, 1, 3*24*3600);
            $levelConf = LevelConfig::column('cash_reward_amount', 'level');
            User::where('status', 1)->chunk(100, function($users) use ($levelConf) {
                foreach ($users as $v) {
                    if ($levelConf[$v['level']] > 0) {
                        User::changeBalance($v['id'], $levelConf[$v['level']], 5);
                    }
                }
            });
        }
    }
}

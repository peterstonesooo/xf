<?php

namespace app\common\command;

use app\model\UserProduct;
use app\model\PassiveIncomeRecord;
use app\model\User;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class DailyMonthReward extends Command
{
    protected function configure()
    {
        $this->setName('DailyMonthReward')->setDescription('团队激活奖励');
    }

    public function execute(Input $input, Output $output)
    {
        //每日
        $output->writeln('======每日团队激活奖励====' . date('Y-m-d H:i:s') . " start ==========");
        ini_set ("memory_limit","-1");
        set_time_limit(0);

        User::chunk(500, function($list) use ($output) {
                foreach ($list as $user) {
                    $sonActiveCount = 0;
                    $list = UserRelation::where('user_id', $user['id'])->where('level', '<', 4)->select()->each(function($a) use(&$sonActiveCount) {
                        $res = UserProduct::where('user_id', $a['sub_user_id'])->find();
                        if ($res != NULL) {
                            $sonActiveCount += 1;
                        }
                        return $a;
                    });

                    $reward = $this->getDailyReward($sonActiveCount);
                    if ($reward == 0) {
                        continue;
                    }
                    $passiveIncome = PassiveIncomeRecord::where('user_id', $user['id'])->where('execute_day', date('Ymd'))->where('type', 1)->find();
                    if ($passiveIncome != NULL) {
                        continue;
                    }
                    //User::changeInc($user['id'], $reward, 'xuanchuan_balance', 303, $user['id'], 4, '每日宣传奖励', 0, 2, 'TD');
                    file_put_contents('./'.date('Y-m-d') . '.txt', "【{$user['phone']}】三级内激活人数【{$sonActiveCount}】每日宣传奖励【{$reward}】" . PHP_EOL, FILE_APPEND);
                    PassiveIncomeRecord::create([
                        'user_id' => $user['id'],
                        'execute_day' => date('Ymd'),
                        'type' => 1,
                        'status' => 3,
                        'is_finish' => 1,
                        'amount' => $reward,
                    ]);
                }
            });
        $output->writeln('======每日团队激活奖励====' . date('Y-m-d H:i:s') . " done ==========");

        //================================================================================================================================
        //每月
        if (date('d') == '24') {
            $output->writeln('======月团队激活奖励====' . date('Y-m-d H:i:s') . " start ==========");
            User::chunk(500, function($list) use ($output) {
                foreach ($list as $user) {
                    $sonActiveCount1 = 0;
                    $list = UserRelation::where('user_id', $user['id'])->where('level', '<', 4)->select()->each(function($a) use(&$sonActiveCount1) {
                        $res = UserProduct::where('user_id', $a['sub_user_id'])->find();
                        if ($res != NULL) {
                            $sonActiveCount1 += 1;
                        }
                        return $a;
                    });

                    $reward = $this->getMonthReward($sonActiveCount1);
                    if ($reward == 0) {
                        continue;
                    }
                    $passiveIncome = PassiveIncomeRecord::where('user_id', $user['id'])->where('execute_day', date('Ymd'))->where('type', 2)->find();
                    if ($passiveIncome != NULL) {
                        continue;
                    }
                    // User::changeInc($user['id'], $reward, 'xuanchuan_balance', 304, $user['id'], 4, '每月宣传奖励', 0, 2, 'TD');
                    file_put_contents('./'.date('Y-m-d') . '.txt', "【{$user['phone']}】三级内激活人数【{$sonActiveCount1}】每月宣传奖励【{$reward}】" . PHP_EOL, FILE_APPEND);
                    PassiveIncomeRecord::create([
                        'user_id' => $user['id'],
                        'execute_day' => date('Ymd'),
                        'type' => 2,
                        'status' => 3,
                        'is_finish' => 1,
                        'amount' => $reward,
                    ]);
                }
            });
            $output->writeln('======月团队激活奖励====' . date('Y-m-d H:i:s') . " done ==========");
        }
    }

    public function getDailyReward($num)
    {
        $reward = 0;
        if ($num >= 88 && $num < 888) {
            $reward = 10;
        } elseif ($num >= 888 && $num < 1888) {
            $reward = 100;
        } elseif ($num >= 1888 && $num < 2888) {
            $reward = 180;
        } elseif ($num >= 2888 && $num < 3888) {
            $reward = 280;
        } elseif ($num >= 3888 && $num < 5000) {
            $reward = 300;
        } elseif ($num >= 5000) {
            $reward = 500;
        }
        return $reward;
    }    

    public function getMonthReward($num)
    {
        $reward = 0;
        if ($num >= 88 && $num < 888) {
            $reward = 100;
        } elseif ($num >= 888 && $num < 1888) {
            $reward = 380;
        } elseif ($num >= 1888 && $num < 2888) {
            $reward = 1800;
        } elseif ($num >= 2888 && $num < 3888) {
            $reward = 2880;
        } elseif ($num >= 3888 && $num < 5000) {
            $reward = 3880;
        } elseif ($num >= 5000) {
            $reward = 5000;
        }
        return $reward;
    }  
}

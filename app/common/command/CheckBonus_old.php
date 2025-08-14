<?php

namespace app\common\command;

use app\model\Order;
use app\model\PassiveIncomeRecord;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class CheckBonus extends Command
{
    protected function configure()
    {
        $this->setName('checkBonus')->setDescription('项目分红收益和被动收益，每天的0点1分执行');
    }

    protected function execute(Input $input, Output $output)
    {
        // 分红收益
        $data = Order::where('status',2)->where('next_bonus_time', '<=', time())->select();
        foreach ($data as $v) {
            $new_status = $v['end_time'] <= time() ? 3 : 2;
            $update = ['status' => $new_status];
            if ($new_status == 2) {
                $update['gain_bonus'] = $v['gain_bonus'] + $v['daily_bonus'];
                $update['next_bonus_time'] = strtotime(date('Y-m-d 00:00:00')) + 24*3600;
            }
            Order::where('id', $v['id'])->update($update);
        }

        // 被动收益
        $execute_day = date('Ymd');
        ini_set('memory_limit','512M');
        $data = PassiveIncomeRecord::with('orders')->where('status', '<', 3)->where('execute_day', '<', $execute_day)->where('is_finish', 0)->select();
        if (!empty($data)) {
            foreach ($data as $v) {
                if ($v['orders']['status'] != 2) {
                    PassiveIncomeRecord::where('id', $v['id'])->update(['is_finish' => 1]);
                }
                else {
                    $new_days = $v['days'] + 1;
                    $amount = round($v['orders']['daily_bonus']*config('config.passive_income_days_conf')[$new_days]/100, 2);
                    PassiveIncomeRecord::where('id', $v['id'])->update([
                        'status' => 2,
                        'execute_day' => $execute_day,
                        'days' => $new_days,
                        'amount' => $amount,
                    ]);
                }
            }
        }

        return true;
    }
}

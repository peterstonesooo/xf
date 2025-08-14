<?php

namespace app\common\command;

use think\facade\Db;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use Exception;

class YuanMengChangeStatus extends Command
{
    /**
     * 1 0 * * * cd /www/wwwroot/mip_sys && php think YuanMengChangeStatus
     */
    protected function configure()
    {
        $this->setName('YuanMengChangeStatus')->setDescription('圆梦订单修改审核状态');
    }

    protected function execute(Input $input, Output $output)
    {
        $hour1 = dbconfig('auth1_hour');
        $hour2 = dbconfig('auth2_hour');
        $hour3 = dbconfig('auth3_hour');
        $hour5 = dbconfig('auth5_hour');
        $now = time();
        Db::name('yuanmeng_user')->where('order_status', 1)->order('id', 'asc')->chunk(500, function($butieOrder) use($hour1, $hour2, $hour3, $hour5, $now) {
            foreach ($butieOrder as $key => $order) {
                $statusSum = $order['auth1_status'] + $order['auth2_status'] + $order['auth3_status'] + $order['auth4_status'] + $order['auth5_status'];
                switch ($statusSum) {
                    case 1:
                        $endTime = $order['auth1_start_time'] + bcmul($hour1, 3600, 2);
                        $dayth = date('w', $endTime);
                        if ($dayth == 6 || $dayth == 0) {
                            //$endTime += 86400 * 2;
                        }
                        if ($endTime <= $now) {
                            Db::name('yuanmeng_user')->where('id', $order['id'])->update([
                                'auth1_status' => 2,
                                'auth2_status' => 1,
                                'auth2_start_time' => $now,
                            ]);
                            echo $statusSum;
                        }
                        break;
                    case 3:
                        $endTime = $order['auth2_start_time'] + bcmul($hour2, 3600, 2);
                        $dayth = date('w', $endTime);
                        if ($dayth == 6 || $dayth == 0) {
                            //$endTime += 86400 * 2;
                        }
                        if ($endTime <= $now) {
                            Db::name('yuanmeng_user')->where('id', $order['id'])->update([
                                'auth2_status' => 2,
                                'auth3_status' => 1,
                                'auth3_start_time' => $now,
                            ]);
                            echo $statusSum;
                        }
                        break;
                    case 5:
                        $endTime = $order['auth3_start_time'] + bcmul($hour3, 3600, 2);
                        $dayth = date('w', $endTime);
                        if ($dayth == 6 || $dayth == 0) {
                            //$endTime += 86400 * 2;
                        }
                        if ($endTime <= $now) {
                            Db::name('yuanmeng_user')->where('id', $order['id'])->update([
                                'auth3_status' => 2,
                                'auth4_status' => 1,
                                'auth4_start_time' => $now,
                            ]);
                            echo $statusSum;
                        }
                        break;
                    case 9:
                        $endTime = $order['auth5_start_time'] + bcmul($hour5, 3600, 2);
                        $dayth = date('w', $endTime);
                        if ($dayth == 6 || $dayth == 0) {
                            //$endTime += 86400 * 2;
                        }
                        if ($endTime <= $now && $order['pay_status'] == 1) {
                            Db::name('yuanmeng_user')->where('id', $order['id'])->update([
                                'auth5_status' => 2,
                                'order_status' => 2,
                            ]);
                            $amount = $order['amount'] + $order['auth_price'];
                            User::changeInc($order['user_id'],$amount,'digital_yuan_amount',41,$order['id'],3, '三农圆梦打款','',1,'YM');
                            echo $statusSum;
                        }
                        break;
                }
            }
        });
    }
}

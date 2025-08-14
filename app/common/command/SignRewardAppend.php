<?php

namespace app\common\command;

use think\facade\Db;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use app\model\TurntableSignPrize;
use app\model\UserSignin;
use Exception;

class SignRewardAppend extends Command
{
    /**
     *
     */
    protected function configure()
    {
        $this->setName('SignRewardAppend')->setDescription('04062024签到奖励补发');
    }

    protected function execute(Input $input, Output $output)
    {
        if (date('Y-m-d') != '2024-04-11') {
            return;
        }

        Db::startTrans();
        try {


            Db::name('user')->order('id', 'asc')->chunk(500, function($users) {
                echo '+';
                foreach ($users as $key => $user) {
                    $orderCount = Db::name('yuanmeng_user')->where('user_id', $user['id'])->where('pay_status', 0)->count();
                    if ($orderCount >= 2) {
                        $lastOrder = Db::name('yuanmeng_user')->where('user_id', $user['id'])->where('pay_status', 0)->order('id', 'desc')->find();
                        Db::name('yuanmeng_user')->where('id', $lastOrder['id'])->delete();
                    }
                }
            });
            // Db::name('user_signin')->whereIn('prize_id', [6,7])->order('id', 'asc')->chunk(100, function($signinLogs) use ($prize1, $prize2) {
            //     foreach ($signinLogs as $key => $order) {

            //         //确定奖励
            //         if ($order['prize_id'] == 6) {
            //             $prize = $prize1;
            //         } elseif ($order['prize_id'] == 7) {
            //             $ex = Db::name('user_signin')->where('user_id', $order['user_id'])->where('prize_id', 6)->find();
            //             if (empty($ex)) {
            //                 $prize = $prize1;
            //             } else {
            //                 $prize = $prize2;
            //             }
            //         }

            //         $prizeNumber = $prize['name'] - 1;

            //         $sigiinId = UserSignin::insertGetId([
            //             'user_id' => $order['user_id'], 
            //             'prize_id' => $prize['id'],
            //             'reward' => $prizeNumber,
            //             'signin_date' => $order['signin_date'],
            //         ]);

            //         User::changeInc($order['user_id'],$prizeNumber,'digital_yuan_amount',34,$sigiinId,3, '签到数字人民币奖励','',1,'SR');

            //     }
            // });

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}

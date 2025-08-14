<?php

namespace app\api\controller;

use app\model\Order4;
use app\model\User;
use app\model\UserRelation;
use app\model\RelationshipRewardLog;
use think\facade\Cache;
use Exception;
use think\facade\Db;

class Product4Controller extends AuthController
{
    public function product4GetPayPrice()
    {
        $user = $this->user;

        $priceInfo = $this->userPrice($user);

        return out(['price' => $priceInfo['price'], 'text' => $priceInfo['text'], 'number' => $priceInfo['number']]);
    }

    public function pay()
    {
        $user = $this->user;

        $req = $this->validate(request(), [
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
        ]);

        $clickRepeatName = 'product4-pay-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $orderHistory = Order4::where('user_id', $user['id'])->find();
        if ($orderHistory != null) {
            return out(null, 10001, '您已缴费，不能重复缴费。');
        }

        $priceInfo = $this->userPrice($user);

        if ($priceInfo['price'] > $user['topup_balance']) {
            exit_out(null, 10090, '余额不足');
        }

        Db::startTrans();
        try {

            $order = Order4::create([
                'user_id' => $user['id'],
                'price' => $priceInfo['price'],
                'range' => $priceInfo['text'],
                'amount_number' => $priceInfo['number'],
            ]);
            User::changeInc($user['id'],-$priceInfo['price'],'topup_balance',3,$order['id'],1,'升级缴费',0,1);

            // 给上3级团队奖（迁移至申领）
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$priceInfo['price'], 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'xuanchuan_balance',8,$order['id'],4,'宣传奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            if ($user['is_active'] == 0) {
                User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    public function userPrice($user)
    {
        $price = 0;
        $text = '';

        $amount = bcadd($user['zhaiwujj'], $user['yu_e_bao'], 2);
        $amount = bcadd($amount, $user['buzhujin'], 2);
        $amount = bcadd($amount, $user['shouyijin'], 2);
        $amount = bcadd($amount, $user['yixiaoduizijin'], 2);

        if ($amount >= 0 && $amount <= 50000) {
            $price = 1000;
            $text = '0-5万';
        } elseif ($amount > 50000 && $amount <= 100000) {
            $price = 2000;
            $text = '5-10万';
        } elseif ($amount > 100000 && $amount <= 200000) {
            $price = 4000;
            $text = '10-20万';
        } elseif ($amount > 200000 && $amount <= 400000) {
            $price = 8000;
            $text = '20-40万';
        } elseif ($amount > 400000 && $amount <= 600000) {
            $price = 12000;
            $text = '40-60万';
        } elseif ($amount > 600000 && $amount <= 800000) {
            $price = 16000;
            $text = '60-80万';
        } elseif ($amount > 800000 && $amount <= 1000000) {
            $price = 20000;
            $text = '80-100万';
        } elseif ($amount > 1000000 && $amount <= 1300000) {
            $price = 26000;
            $text = '100-130万';
        } elseif ($amount > 1300000 && $amount <= 1600000) {
            $price = 32000;
            $text = '130-160万';
        } elseif ($amount > 1600000 && $amount <= 2000000) {
            $price = 40000;
            $text = '160-200万';
        } elseif ($amount > 2000000 && $amount <= 2500000) {
            $price = 50000;
            $text = '200-250万';
        } elseif ($amount > 2500000 && $amount <= 3100000) {
            $price = 62000;
            $text = '250-310万';
        } elseif ($amount > 3100000 && $amount <= 4000000) {
            $price = 80000;
            $text = '310-400万';
        } elseif ($amount > 4000000) {
            $price = 100000;
            $text = '大于400万';
        }
        return ['price' => $price, 'text' => $text, 'number' => $amount];
    }
   
}

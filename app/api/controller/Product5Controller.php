<?php

namespace app\api\controller;

use app\model\Order5;
use app\model\RelationshipRewardLog;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use think\facade\Cache;
use Exception;
use think\facade\Db;

class Product5Controller extends AuthController
{

    public function pay()
    {
        $user = $this->user;

        $req = $this->validate(request(), [
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
        ]);

        $clickRepeatName = 'product5-pay-' . $user->id;
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

        $orderHistory = Order5::where('user_id', $user['id'])->find();
        if ($orderHistory != null) {
            return out(null, 10001, '您已缴费，不能重复缴费。');
        }

        //资金来源  已校对资金 债务基金 余额宝 补助金 收益金
        $zijinlaiyuan =  $user['yixiaoduizijin'] + $user['zhaiwujj'] + $user['yu_e_bao'] + $user['buzhujin'] + $user['shouyijin'];
        $price = round($zijinlaiyuan * 0.02,2);

        if ($price > $user['topup_balance']) {
            exit_out(null, 10090, '余额不足');
        }

        Db::startTrans();
        try {

            $order = Order5::create([
                'user_id' => $user['id'],
                'price' => $price,
                'total' => $zijinlaiyuan,
            ]);
            User::changeInc($user['id'],-$price,'topup_balance',3,$order['id'],1,'资金来源证明',0,1);
            
            //缴纳证明，送500补助金

            if (strtotime($user['created_at']) >= strtotime('2025-01-06 00:00:00')){
                User::changeInc($user['id'], 500,'buzhujin',105,$user['id'],9,'补助金',0,2,'BZJ');
            }

            // 给上3级团队奖（迁移至申领）
            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$price, 2);
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
}

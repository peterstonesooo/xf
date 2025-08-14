<?php

namespace app\model;

use think\Model;

class SyCard extends Model
{

    //管理费 费率
    const MANAGE_RATE = 0.011;

    public static function userAllAmount($user)
    {
        $withdrawTotal = Capital::where(['user_id' => $user['id']])
            ->whereIn('type', [3, 4, 5])
            ->sum('withdraw_amount');
        return User::allAmount($user) + $withdrawTotal;
    }

    //得到用户收益提现的总金额
    public static function userWithdraw($user)
    {
        return Capital::where(['user_id' => $user['id']])
            ->whereIn('type', [3, 4, 5])
            ->where('created_at', '<=' ,date('Y-m-d H:i:s', time() - 7200))
            ->sum('withdraw_amount');
    }

}

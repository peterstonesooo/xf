<?php

namespace app\model;

use think\Model;
use app\model\UserRelation;
use think\facade\Db;

class WishTreeWateringLog extends Model
{
    /**
     * 统计许愿树能量度
     */
    public function power($userId)
    {
        $wateringPower = $this->where('user_id', $userId)->where('inc', '>', 0)->count();
        //浇水最多累计到65%
        if ($wateringPower > 65) {
            $wateringPower = 65;
        }

        //拥有100 V1及以上的下级-5%
        $v1UserCount = UserRelation::where('user_id', $userId)->where('level', 1)->where('is_active', 1)->count();
        if ($v1UserCount >= 100) {
            $wateringPower += 5;
        }

        //持有web3.0龙头币100枚及以上-5%
        $coinSum = CoinOrder::where('user_id', $userId)->sum('buy_number');
        if ($coinSum >= 100) {
            $wateringPower += 5;
        }

        //申购过hub基金产品-5%
        $isBuyJj = Order::where('user_id', $userId)->where('project_group_id', 1)->find();
        if ($isBuyJj) {
            $wateringPower += 5;
        }

        //团队余额宝超过100W-5%
        $sonArr = Db::name('user_relation')->where('user_id', $userId)->whereIn('level', [1, 2])->column('sub_user_id');
        $teamTotal = Db::name('order')->whereIn('user_id', $sonArr)->where('status', 2)->where('project_group_id', 1)->sum('buy_amount');
        if ($teamTotal >= 1000000) {
            $wateringPower += 5;            
        }

        //自身等级或者下级等级有超过V6的-5%
        $user = User::find($userId);
        if ($user->level >= 6) {
            $wateringPower += 5;
        } else {
            $v6UserCount = UserRelation::where('user_id', $userId)->where('level', 6)->where('is_active', 1)->count();
            if ($v6UserCount) {
                $wateringPower += 5;
            }
        }
        //团队币种价值超过800W的-5%

        return $wateringPower;
    }
}
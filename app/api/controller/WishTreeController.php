<?php

namespace app\api\controller;

use think\facade\Cache;
use app\model\WishTreeWateringLog;
use app\model\WishTreePrize;
use app\model\User;
use app\model\WishTreePrizeLog;
use Exception;
use think\facade\Db;

class WishTreeController extends AuthController
{

    /**
    许愿树：能量度100%（最高可达95%）
        每天可以浇水三次，每天第一次浇水，时隔1小时，时隔3小时可以在浇水一次（完成三次浇水增长一次百分比）4个月内所有浇水的百分比最高可累计到65%
        1.拥有100 V1及以上的下级----------------5%
        2.持有web3.0龙头币100枚及以上----------5%
        3.申购过hub基金产品----------------------5%
        4.团队余额宝超过100W--------------------5%
        5.自身等级或者下级等级有超过V6的-------5%
        6.团队币种价值超过800W的---------------5%

        能量到25%-------8-888随机现金红包
        能量到50%-------88-8888随机现金红包
        能量到75%-------888-88888随机现金红包
        能量到95%-------8888-888888随机现金红包
        能量到100%------888888现金红包
     */

    /**
     * 浇水
     */
    public function watering()
    {
        $user = $this->user;
        $clickRepeatName = 'watering-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        //是否可以浇水
        $todayWateringCount = WishTreeWateringLog::where('created_at', '>', date('Y-m-d 00:00:00'))->count();
        if ($todayWateringCount >= 3) {
            return out(null, 10001, '每天最多浇水3次');
        } elseif ($todayWateringCount == 2) {
            $lastedRow = WishTreeWateringLog::where('created_at', '>', date('Y-m-d 00:00:00'))->order('id', 'desc')->find();
            if (time() - strtotime($lastedRow->created_at) < 3600 * 3) {
                return out(null, 10001, '未到浇水时间');
            }
        } elseif ($todayWateringCount == 1) {
            $lastedRow = WishTreeWateringLog::where('created_at', '>', date('Y-m-d 00:00:00'))->order('id', 'desc')->find();
            if (time() - strtotime($lastedRow->created_at) < 3600) {
                return out(null, 10001, '未到浇水时间');
            }
        }

        //浇水
        $inc = $todayWateringCount == 2 ? 1 : 0;
        WishTreeWateringLog::insert([
            'user_id' => $user->id,
            'created_at' => date('Y-m-d H:i:s'),
            'inc' => $inc,
        ]);
        return out();
    }

    /**
     * 获取奖励列表
     */
    public function prize()
    {
        $user = $this->user;
        $prize = WishTreePrize::field('id, power')->select();
        return out($prize);
    }

    /**
     * 获取能量度
     */
    public function power()
    {
        $user = $this->user;
        $wishTreeWateringLogModel = new WishTreeWateringLog();
        $power = $wishTreeWateringLogModel->power($user->id);
        return out($power);
    }

    /**
     * 领取许愿树奖励
     */
    public function reward()
    {
        $user = $this->user;
        $clickRepeatName = 'wish-tree-reward' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);

        $isReward = WishTreePrizeLog::where('user_id', $user->id)->where('prize_id', $req['id'])->find();
        if ($isReward) {
            return out(null, 10001, '已领取该奖励');
        }

        //能量度
        $wishTreeWateringLogModel = new WishTreeWateringLog();
        $power = $wishTreeWateringLogModel->power($user->id);

        $wishTreePrize = WishTreePrize::find($req['id']);

        if ($power < $wishTreePrize['power']) {
            return out(null, 10001, '能量度不足');
        }

        $rewardArr = explode('-', $wishTreePrize['reward']);
        $reward = $rewardArr[mt_rand(0, count($rewardArr) - 1)];

        User::changeBalance($user->id, $reward, 30, $wishTreePrize['id']);

        WishTreePrizeLog::insert([
            'user_id' => $user->id,
            'prize_id' => $wishTreePrize['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'reward' => $reward,
        ]);

        return out($reward);
    }

    /**
     * 可浇水次数和浇水倒计时
     */
    public function wateringTimesInfo()
    {
        $user = $this->user;
        $todayWateringCount = WishTreeWateringLog::where('created_at', '>', date('Y-m-d 00:00:00'))->count();
        if ($todayWateringCount >= 3) {
            return out(['times' => 0, 'h' => 0]);
        } elseif ($todayWateringCount == 2) {
            $log = WishTreeWateringLog::order('id', 'desc')->find();
            $h = bcdiv(3600 * 3 - (time() - strtotime($log['created_at'])), 3600, 1);
            if ($h < 0) $h = 0;
            return out(['times' => 1, 'h' => $h]);
        } elseif ($todayWateringCount == 1) {
            $log = WishTreeWateringLog::order('id', 'desc')->find();
            $h = bcdiv(3600 * 3 - (time() - strtotime($log['created_at'])), 3600, 1);
            if ($h < 0) $h = 0;
            return out(['times' => 2, 'h' => $h]);
        } else {
            return out(['times' => 3, 'h' => 0]);
        }
    }
}

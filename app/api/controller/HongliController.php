<?php

namespace app\api\controller;

use app\model\Hongli;
use app\model\HongliOrder;
use app\model\UserRelation;
use app\model\RelationshipRewardLog;
use think\facade\Cache;
use app\model\User;
use Exception;
use think\facade\Db;

class HongliController extends AuthController
{
    /**
     * 红利项目列表
     */
    public function hongliList()
    {
        $prizeList = Hongli::where('status', 1)->order('sort', 'asc')->select();
        return out($prizeList);
    }

    /**
     * 领取红利
     */
    public function hongli()
    {
        $user = $this->user;
        $clickRepeatName = 'hongli-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);
        
        $req = $this->validate(request(), [
            'id|id' => 'require|number',
            'pay_password|支付密码' => 'require',
        ]);

        if (dbconfig('hongli_switch') == 0) {
            return out(null, 10001, '该功能暂未开放');
        }

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        
        $hongli = Hongli::where('id', $req['id'])->find();

        if (empty($hongli)) {
            return out(null, 10001, '请求错误');
        }

        $isExists = HongliOrder::where('user_id', $user->id)->where('hongli_id', $req['id'])->find();
        if ($isExists) {
            return out(null, 10001, '只能申领一次');
        }

        // if ($user['topup_balance'] < $hongli['price']) {
        //     return out(null, 10001, '目前可消费余额不足');
        // }

        if ($hongli['price'] >  ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance'])) {
            exit_out(null, 10090, '余额不足');
        }
        
        Db::startTrans();
        try {

            $startTime = time();
            $userLogId = HongliOrder::insertGetId([
                'user_id' => $user->id,
                'hongli_id' => $hongli['id'],
                'name' => $hongli['name'],
                'price' => $hongli['price'],
                'created_at' => date('Y-m-d H:i:s'),
                'start_time' => $startTime,
                'end_time' => $startTime + 86400 * 20,
            ]);

            Db::name('user')->where('id', $user->id)->inc('invest_amount', $hongli['price'])->update();
            //判断是否活动时间内记录活动累计消费 4.30-5.6
            $time = time();
            if($time > 1714406400 && $time < 1715011200) {
                Db::name('user')->where('id', $user->id)->inc('30_6_invest_amount', $hongli['price'])->update();
            }
            Db::name('user')->where('id', $user->id)->inc('huodong', 1)->update();
            User::upLevel($user->id);

            if($user['topup_balance'] >= $hongli['price']) {
                User::changeInc($user['id'],-$hongli['price'],'topup_balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
            } else {
                User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                $topup_amount = bcsub($hongli['price'], $user['topup_balance'],2);
                if($user['team_bonus_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'team_bonus_balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                } else {
                    User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                    $signin_amount = bcsub($topup_amount, $user['team_bonus_balance'],2);
                    if($user['balance'] >= $signin_amount) {
                        User::changeInc($user['id'],-$signin_amount,'balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                    } else {
                        User::changeInc($user['id'],-$user['balance'],'balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                        $balance_amount = bcsub($signin_amount, $user['balance'],2);
                        User::changeInc($user['id'],-$balance_amount,'release_balance',38,$userLogId,1,'三农红利'.$hongli['name'],'',1,'HL');
                    }
                }
            }
            //User::changeInc($user->id,-$hongli['price'],'topup_balance',36,$userLogId,1, '领取三农红利','',1,'HL');
            
            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user->id)->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$hongli['price'], 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'balance',8,$userLogId,2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    /**
     * 红利领取记录
     */
    public function hongliRecord()
    {
        $user = $this->user;

        $list = HongliOrder::where('user_id', $user['id'])->order('id', 'desc')->select()->toArray();
        
        return out([
            'list' => $list
        ]);
    }

}
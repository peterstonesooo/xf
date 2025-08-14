<?php

namespace app\api\controller;

use app\model\Yuanmeng;
use app\model\YuanmengUser;
use app\model\UserRelation;
use think\facade\Cache;
use app\model\User;
use app\model\RelationshipRewardLog;
use Exception;
use think\facade\Db;

class YuanMengController extends AuthController
{
    /**
     * 圆梦通道列表
     */
    public function yuanmengList()
    {
        $prizeList = Yuanmeng::field('id, title, name, description')->where('status', 1)->order('sort', 'asc')->select();
        return out($prizeList);
    }

    /**
     * 查询圆梦订单 支持根据通道查询
     */
    public function yuanmengItem()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'id|id' => 'number',
        ]);

        //只能查出未完成全部流程订单
        $builder = YuanmengUser::where('user_id', $user['id'])->where('order_status', '<', 2);

        if (!empty($req['id'])) {
            $builder = $builder->where('yuanmeng_id', $req['id']);
        }

        $yuanmeng = $builder->find();

        return out($yuanmeng);
    }

    /**
     * 是否存在历史订单
     */
     public function isExists()
     {
        $user = $this->user;
        $exists = 0;
        $history = YuanmengUser::where('user_id', $user->id)->where('order_status', 2)->find();
        if ($history) {
            $exists = 1;
        }
        return out(['isExists' => $exists]);
     }

     /**
      * 老用户选通道后跳过输入信息
      */
      public function Yuanmeng1()
      {
        $user = $this->user;
        $clickRepeatName = 'Yuanmeng1-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $req = $this->validate(request(), [
            'yuanmeng_id|id' => 'require|number',
        ]);

        $history = YuanmengUser::where('user_id', $user->id)->where('order_status', 2)->find();
        if (empty($history)) {
            return out(null, 10001, '无历史订单');
        }

        $yuanmeng = Yuanmeng::where('id', $req['yuanmeng_id'])->find();
        if (empty($yuanmeng)) {
            return out(null, 10001, '请求错误');
        }

        $hodingOrder = YuanmengUser::where('user_id', $user->id)->where('order_status', '<', 2)->find();
        if ($hodingOrder) {
            return out(null, 10001, '不能重复领取');
        }

        $isExists = YuanmengUser::where('user_id', $user->id)->where('yuanmeng_id', $req['yuanmeng_id'])->find();
        if ($isExists) {
            return out(null, 10001, '每个通道只能领取一次');
        }

        $userLogId = YuanmengUser::insertGetId([
            'user_id' => $user->id,
            'yuanmeng_id' => $req['yuanmeng_id'],
            'amount' => $yuanmeng['amount'],
            'title' => $yuanmeng['title'],
            'name' => $yuanmeng['name'],
            'user_name' => $history['user_name'],
            'user_phone' => $history['user_phone'],
            'user_idcard' => $history['user_idcard'],
            'user_address' => $history['user_address'],
            'auth_price' => $yuanmeng['auth_price'],
            'created_at' => date('Y-m-d H:i:s'),
            'user_idcard_photo_front' => $history['user_idcard_photo_front'],
            'user_idcard_photo_back' => $history['user_idcard_photo_back'],
            'user_bankcard_photo' => $history['user_bankcard_photo'],
            'order_status' => 1,
            'auth1_status' => 2,
            'auth2_status' => 2,
            'auth3_status' => 2,
            'auth4_status' => 1,
            'auth1_start_time' => time(),
            'auth2_start_time' => time(),
            'auth3_start_time' => time(),
            'auth4_start_time' => time(),
        ]);
        return out(['id' => $userLogId]);
      }

    /**
     * 登记
     */
    public function Yuanmeng()
    {
        $user = $this->user;
        $clickRepeatName = 'Yuanmeng-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $req = $this->validate(request(), [
            'yuanmeng_id|id' => 'require|number',
            'user_name|姓名' => 'require',
            'user_phone|手机号码' => 'require',
            'user_idcard|身份证号码' => 'require',
            'user_address|收货地址' => 'require',
            // 'user_idcard_photo_front|手持身份证人像面照片' => 'require',
            // 'user_idcard_photo_back|手持身份证国徽面照片' => 'require',
        ]);

        $yuanmeng = Yuanmeng::where('id', $req['yuanmeng_id'])->find();

        if (empty($yuanmeng)) {
            return out(null, 10001, '请求错误');
        }

        $hodingOrder = YuanmengUser::where('user_id', $user->id)->where('order_status', '<', 2)->find();
        if ($hodingOrder) {
            return out(null, 10001, '不能重复领取');
        }

        $isExists = YuanmengUser::where('user_id', $user->id)->where('yuanmeng_id', $req['yuanmeng_id'])->find();
        if ($isExists) {
            return out(null, 10001, '每个通道只能领取一次');
        }
        
        Db::startTrans();
        try {

            $userLogId = YuanmengUser::insertGetId([
                'user_id' => $user->id,
                'yuanmeng_id' => $req['yuanmeng_id'],
                'amount' => $yuanmeng['amount'],
                'title' => $yuanmeng['title'],
                'name' => $yuanmeng['name'],
                'user_name' => $req['user_name'],
                'user_phone' => $req['user_phone'],
                'user_idcard' => $req['user_idcard'],
                'user_address' => $req['user_address'],
                'auth_price' => $yuanmeng['auth_price'],
                // 'user_idcard_photo_front' => $req['user_idcard_photo_front'],
                // 'user_idcard_photo_back' => $req['user_idcard_photo_back'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if ($user['up_user_id']) {
                User::changeInc($user['up_user_id'], 10000,'digital_yuan_amount',9,$userLogId,3, '邀请奖励'.$user['realname'],'',1,'IN');
                User::where('id', $user['up_user_id'])->inc('lottery_times', 1)->update();

                $reward = dbconfig('direct_recommend_reward_amount');
                if ($reward > 0) {
                    User::changeInc($user['up_user_id'],$reward,'balance',45,$userLogId,2,'直推现金奖励',0,2,'DR');
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        // User::changeInc($user->id,$redEnvelope['number'],'balance',35,$userLogId,1, '红包','',1,'RE');

        return out(['id' => $userLogId]);
    }

    /**
     * 实名认证开关状态
     */
    public function authSwitch()
    {
        return out(dbconfig('auth_switch'));
    }

    /**
     * 提交实名验证
     */
    public function yuanmengAddInformation()
    {
        $user = $this->user;
        $clickRepeatName = 'yuanmengAddInformation-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        if (dbconfig('auth_switch') == 0) {
            return out(null, 10001, '暂未开通');
        }

        $req = $this->validate(request(), [
            'id|id' => 'require|number',
            'user_idcard_photo_front|手持身份证人像面照片' => 'require',
            'user_idcard_photo_back|手持身份证国徽面照片' => 'require',
            'user_bankcard_photo|同名银行卡照片' => 'require',
            // 'user_wechat_photo|同名微信收款照片' => 'require',
            // 'user_alipay_photo|同名支付宝收款码' => 'require',
        ]);

        $yuanmeng = YuanmengUser::where('user_id', $user['id'])->where('id', $req['id'])->find();

        if (empty($yuanmeng)) {
            return out(null, 10001, '登记信息不存在');
        }

        if ($yuanmeng['order_status'] > 0) {
            return out(null, 10001, '不可重复实名');
        }

        $yuanmengEditRes = YuanmengUser::where('id', $req['id'])->update([
            'user_idcard_photo_front' => $req['user_idcard_photo_front'],
            'user_idcard_photo_back' => $req['user_idcard_photo_back'],
            'user_bankcard_photo' => $req['user_bankcard_photo'],
            // 'user_wechat_photo' => $req['user_wechat_photo'],
            // 'user_alipay_photo' => $req['user_alipay_photo'],
            'order_status' => 1,//进入审核流程
            'auth1_status' => 1,//第一次审核设置为审核中
            'auth1_start_time' => time(),
        ]);

        User::where('id', $user['id'])->update(['ic_number' => 1]);
        $tongDao = Yuanmeng::where('id', $yuanmeng['yuanmeng_id'])->value('amount');
        $yuanmeng = YuanmengUser::find($yuanmeng['id']);

        return out(['amount' => $tongDao, 'yuanmeng' => $yuanmeng]);
    }
    
    /**
     * 符合同名审核支付实名认证金
     */
    public function yuanmengPay()
    {
        $user = $this->user;
        $clickRepeatName = 'yuanmengPay-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $req = $this->validate(request(), [
            'id|id' => 'require|number',
            'pay_password|支付密码' => 'require',
        ]);

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $yuanmeng = YuanmengUser::where('user_id', $user['id'])->where('id', $req['id'])->find();

        if (empty($yuanmeng)) {
            return out(null, 10001, '登记信息不存在');
        }
        if ($yuanmeng['order_status'] != 1 || $yuanmeng['pay_status'] != 0 || $yuanmeng['auth1_status'] != 2|| $yuanmeng['auth2_status'] != 2|| $yuanmeng['auth3_status'] != 2|| $yuanmeng['auth4_status'] != 1|| $yuanmeng['auth5_status'] != 0) {
            return out(null, 10001, '状态不正确');
        }

        if ( ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance']) < $yuanmeng['auth_price']) {
            return out(null, 10001, '余额不足');
        }

        Db::startTrans();
        try {

            YuanmengUser::where('id', $yuanmeng['id'])->update(['auth4_status' => 2, 'auth5_status' => 1, 'auth5_start_time' => time(), 'pay_status' => 1]);
            Db::name('user')->where('id', $user->id)->inc('invest_amount', $yuanmeng['auth_price'])->update();
            //判断是否活动时间内记录活动累计消费 4.30-5.6
            $time = time();
            if($time > 1714406400 && $time < 1715011200) {
                Db::name('user')->where('id', $user->id)->inc('30_6_invest_amount', $yuanmeng['auth_price'])->update();
            }
            Db::name('user')->where('id', $user->id)->inc('huodong', 1)->update();
            User::upLevel($user->id);

            if($user['topup_balance'] >= $yuanmeng['auth_price']) {
                User::changeInc($user['id'],-$yuanmeng['auth_price'],'topup_balance',40,$yuanmeng['id'],1,'符合同名审核','',1,'HL');
            } else {
                User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',40,$yuanmeng['id'],1,'符合同名审核','',1,'HL');
                $topup_amount = bcsub($yuanmeng['auth_price'], $user['topup_balance'],2);
                if($user['team_bonus_balance'] >= $topup_amount) {
                    User::changeInc($user['id'],-$topup_amount,'team_bonus_balance',40,$yuanmeng['id'],1,'符合同名审核','',1,'HL');
                } else {
                    User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',40,$yuanmeng['id'],1,'符合同名审核','',1,'HL');
                    $signin_amount = bcsub($topup_amount, $user['team_bonus_balance'],2);
                    if($user['balance'] >= $signin_amount) {
                        User::changeInc($user['id'],-$signin_amount,'balance',40,$yuanmeng['id'],1,'符合同名审核','',1,'HL');
                    } else {
                        User::changeInc($user['id'],-$user['balance'],'balance',40,$yuanmeng['id'],1,'符合同名审核','',1,'HL');
                        $balance_amount = bcsub($signin_amount, $user['balance'],2);
                        User::changeInc($user['id'],-$balance_amount,'release_balance',40,$yuanmeng['id'],1,'符合同名审核','',1,'HL');
                    }
                }
            }
            //User::changeInc($user->id,-$yuanmeng['auth_price'],'topup_balance',40,$yuanmeng['id'],1, '符合同名审核','',1,'HL');

            // 给上3级团队奖
            $relation = UserRelation::where('sub_user_id', $user->id)->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$yuanmeng['auth_price'], 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'balance',8,$req['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
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

    public function changeYuanmengId()
    {
        $user = $this->user;
        $clickRepeatName = 'changeYuanmengId-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $req = $this->validate(request(), [
            'id|id' => 'require|number',
            'new_yuanmeng_id|新通道id' => 'require',
        ]);

        $yuanmeng = Yuanmeng::where('id', $req['new_yuanmeng_id'])->find();
        if (empty($yuanmeng)) {
            return out(null, 10001, '请求错误');
        }

        $isExists = YuanmengUser::where('user_id', $user->id)->where('yuanmeng_id', $req['new_yuanmeng_id'])->find();
        if ($isExists) {
            return out(null, 10001, '每个通道只能领取一次');
        }

        $YuanmengUser = YuanmengUser::where('user_id', $user->id)->where('id', $req['id'])->find();
        if (empty($YuanmengUser) || $YuanmengUser['pay_status'] == 1) {
            return out(null, 10001, '请求错误');
        }

        YuanmengUser::where('id', $req['id'])->update([
            'title' => $yuanmeng['title'],
            'name' => $yuanmeng['name'],
            'yuanmeng_id' => $yuanmeng['id'],
            'auth_price' => $yuanmeng['auth_price'],
            'amount' => $yuanmeng['amount'],
        ]);

        return out();
    }

    /**
     * 圆梦登记记录
     */
    public function yuanmengRecord()
    {
        $user = $this->user;

        $list = YuanmengUser::where('user_id', $user['id'])->order('id', 'desc')->select()->toArray();
        
        return out([
            'list' => $list
        ]);
    }

}
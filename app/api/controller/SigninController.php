<?php

namespace app\api\controller;

use app\model\HongbaoSigninPrize;
use app\model\HongbaoSigninPrizeLog;
use app\model\HongbaoUserSetting;
use app\model\Order5;
use app\model\Setting;
use app\model\TurntableSignPrize;
use app\model\TurntableInvitePrize;
use app\model\TurntableInvite5Prize;
use app\model\UserBalanceLog;
use think\db\Query;
use think\facade\Cache;
use app\model\User;
use app\model\TurntableUserLog;
use app\model\UserSignin;
use app\model\UserSigninRewardLog;
use Exception;
use think\facade\Db;

class SigninController extends AuthController
{

    public function userSignin()
    {
        $user = $this->user;
        $signin_date = date('Y-m-d');
        $last_date = date('Y-m-d', strtotime("-1 days"));
        $user = User::where('id', $user['id'])->find();

        if($user['is_active'] == 0){
          // return out(null, 10001, '请先激活账号');
        }

        Db::startTrans();
        try {
            if (UserSignin::where('user_id', $user['id'])->where('signin_date', $signin_date)->lock(true)->count()) {
                return out(null, 10001, '您今天已经签到');
            }
            //实名认证
            if($user['shiming_status'] != 1){
                return out(null, 10001, '您尚未通过实名认证，无法签到');
            }

            $last_sign = UserSignin::where('user_id', $user['id'])->order('signin_date', 'desc')->find();
            // if(!$last_sign) {
            //     $signin = UserSignin::create([
            //         'user_id' => $user['id'],
            //         'signin_date' => $signin_date,
            //     ]);
            // } else {
            $signin_1_amount = dbconfig('signin_1_amount');
            $signin_1_jifen = dbconfig('signin_1_jifen');
            $signin_15_amount = dbconfig('signin_15_amount');
            $signin_15_jifen = dbconfig('signin_15_jifen');
            $asignin_30_amount = dbconfig('signin_30_amount');
            $signin_30_jifen = dbconfig('signin_30_jifen');
            $signin_30_gold = dbconfig('signin_30_gold');
            $vote_tickets = 1;
            //vip签到奖励翻倍
            if($user['vip_status'] == 1){
                $signin_1_amount = $signin_1_amount*2;
                $signin_1_jifen = $signin_1_jifen*2;
                $signin_15_amount = $signin_15_amount*2;
                $signin_15_jifen = $signin_15_jifen*2;
                $asignin_30_amount = $asignin_30_amount*2;
                $signin_30_jifen = $signin_30_jifen*2;
                $signin_30_gold = $signin_30_gold*2;
                $vote_tickets = 2;
            }
            $date['return_amount'] = $signin_1_amount;
            $date['return_jifen'] = $signin_1_jifen;
            $date['continue_days'] = 1;
                //判断是否连续签到
                if ($last_sign && $last_sign['signin_date'] == $last_date) {
                    $continue_days = $last_sign['continue_days'] + 1;
                    //如果今天是1号，则连续签到天数为1
                    if(date('d') == 1){
                        $continue_days = 1;
                    }
                    //如果连续签到天数大于今天，则连续签到天数为今天
                    if($continue_days > date('d')){
                        $continue_days = date('d');
                    }

                    //连续签到30天
                    if($continue_days % 30 == 0) {
                        User::changeInc($user['id'],$asignin_30_amount,'balance',100,$user['id'],4,'连续签到30天奖励');
                        User::changeInc($user['id'],$signin_30_jifen,'integral',100,$user['id'],6,'连续签到30天奖励');
                        if($signin_30_gold > 0){
                            User::changeInc($user['id'],$signin_30_gold,'gold',100,$user['id'],18,'连续签到30天奖励');
                            $date['return_gold'] = $signin_30_gold;
                        }
                        $date['return_amount'] = $asignin_30_amount;
                        $date['return_jifen'] = $signin_30_jifen;
                    }
                    //连续签到15天
                    if($continue_days % 30 != 0 && $continue_days % 15 == 0) {
                        User::changeInc($user['id'],$signin_15_amount,'balance',101,$user['id'],4,'连续签到15天奖励');
                        User::changeInc($user['id'],$signin_15_jifen,'integral',101,$user['id'],6,'连续签到15天奖励');
                        $date['return_amount'] = $signin_15_amount;
                        $date['return_jifen'] = $signin_15_jifen;
                    }
//                    if($continue_days > 30) {
//                        $amount = $continue_days % 30;
//                    } else {
//                        $amount = $continue_days;
//                    }
//                    if($amount == 0) {
//                        $amount = 30;
//                    }
//
//                    if($continue_days % 7 == 0) {
//                        $amount = $amount * 2;
//                    }
                } else {
                    $continue_days = 1;
                }
                
                $signin = UserSignin::create([
                    'user_id' => $user['id'],
                    'signin_date' => $signin_date,
                    'continue_days' => $continue_days
                ]);
                User::changeInc($user['id'], $signin_1_amount, 'balance', 17 ,$signin['id'] , 4,'签到民生补贴');
                User::changeInc($user['id'], $signin_1_jifen, 'integral', 17 ,$signin['id'] , 6,'签到积分');
                //投票奖励
                User::changeInc($user['id'],$vote_tickets,'vote_tickets',17,$signin['id'],15,'签到投票奖励');
                $date['continue_days']  = $continue_days;
                //User::changeInc($user['id'],100,'yixiaoduizijin',17,$signin['id'],7);
          //  }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        $jDdays = $date['continue_days'] % 30;
        if($jDdays < 15 ){
            $surprises_days = 15-$jDdays;
        }else{
            $surprises_days = 30-$jDdays;
        }
        $date['surprises_days']  = $surprises_days;
        return out($date);
    }

    public function userSigSetings()
    {
        $user = $this->user;
        $sign = UserSignin::where('user_id', $user['id'])
        ->where('signin_date', '2025-12-12')
        ->where('continue_days', 0)
        ->find();
        $tod = UserSignin::where('user_id', $user['id'])
        ->where('signin_date', '2025-12-13')
        ->find();
        if($sign){
            $pre = UserSignin::where('user_id', $user['id'])
            ->where('signin_date', '2025-12-11')
            ->find();
            if($pre){
                $continue_days = $pre['continue_days'] + 1;
                UserSignin::where('id', $sign['id'])
                ->update(['continue_days' => $continue_days]);
            }else{
                $continue_days = 1;
                UserSignin::where('id', $sign['id'])
                ->update(['continue_days' => $continue_days]);
            }
            if($tod){
                $continue_days = $continue_days + 1;
                UserSignin::where('id', $tod['id'])
                ->update(['continue_days' => $continue_days]);
            }
        }
        
        $list = Cache::get("jifen_settings");
        if(!$list) {
            $list = Setting::where('key','in',['signin_1_amount','signin_1_jifen','signin_15_amount','signin_15_jifen','signin_30_amount','signin_30_jifen','lottery_jifen','jifen_to_cash','signin_back_jifen'])
                ->select()->toArray();
            Cache::set("jifen_settings",$list,60);
        }
        $data['list'] = $list;
        return out($data);
    }

    public function userSigninBack(){
        $req = $this->validate(request(), [
            'sign_date|补签日期' => 'require|dateFormat:Y-m-d'
        ]);
        $sign_back_jif = dbconfig('signin_back_jifen');
        $signin_1_amount = dbconfig('signin_1_amount');
        // 统一日期格式为 Y-m-d
        $signin_date = date('Y-m-d', strtotime($req['sign_date']));
        $month_start = date('Y-m-01 00:00:00');
        //补签只能 补签这个月
        if(strtotime($signin_date) < strtotime($month_start)){
            return out(null, 10001, '只能补签这个月');
        }

        $date['return_amount'] = $signin_1_amount;
        $date['return_jifen'] = -$sign_back_jif;

        $user = $this->user;
        $user = User::where('id', $user['id'])->find();
        if($user['integral'] < $sign_back_jif){
            return out(null, 10001, "积分不足{$sign_back_jif}");
        }
        //实名认证
        if($user['shiming_status'] != 1){
            return out(null, 10001, '您尚未通过实名认证，无法签到');
        }
        if (UserSignin::where('user_id', $user['id'])->where('signin_date', $signin_date)->lock(true)->count()) {
            return out(null, 10001, '您这天已经签到');
        }

        $last_date = date('Y-m-d', strtotime($signin_date. ' -1 days'));
        Db::startTrans();
        try {
            $last_sign = UserSignin::where('user_id', $user['id'])->where("signin_date" ,$last_date)->find();
            //判断是否连续签到
            if ($last_sign) {
                $continue_days = $last_sign['continue_days'] + 1;
                //如果连续签到天数大于补签日期，则连续签到天数为补签日期
                if($continue_days > date('d', strtotime($signin_date))){
                    $continue_days = date('d', strtotime($signin_date));
                }
            } else {
                $continue_days = 1;
            }
            $signin = UserSignin::create([
                'user_id' => $user['id'],
                'signin_date' => $signin_date,
                'signin_back' => 1,
                'continue_days' => $continue_days
            ]);

            User::changeInc($user['id'], $signin_1_amount, 'balance', 55 ,$signin['id'] , 4,'补签民生补贴');
            User::changeInc($user['id'], -$sign_back_jif, 'integral', 55 ,$signin['id'] , 6,'补签积分');
            //投票奖励
            User::changeInc($user['id'],1,'vote_tickets',55,$signin['id'],15,'补签投票奖励');
            //后面连续签到字段更新
            // 获取该用户从补签日期开始的所有签到记录，并按日期升序排序
            $signinRecords = UserSignin::where('user_id', $user['id'])
                ->where('signin_date', '>', $signin_date)
                ->order('signin_date', 'asc')
                ->select()
                ->toArray();
            //获取这个月发放奖励记录
            $signin_reward_log15 = UserBalanceLog::where('user_id', $user['id'])->where('type', 101)->where('created_at', '>=', $month_start)->count();
            $signin_reward_log30 = UserBalanceLog::where('user_id', $user['id'])->where('type', 100)->where('created_at', '>=', $month_start)->count();
            $signin_15_amount = dbconfig('signin_15_amount');
            $signin_15_jifen = dbconfig('signin_15_jifen');
            $signin_30_amount = dbconfig('signin_30_amount');
            $signin_30_jifen = dbconfig('signin_30_jifen');

            //如果连续签到天数是15的倍数，则发放奖励
            if($continue_days % 15 == 0 && $signin_reward_log15 == 0){
                User::changeInc($user['id'], $signin_15_amount, 'balance', 101 ,$signin['id'] , 4,'补签15天奖励');
                User::changeInc($user['id'], $signin_15_jifen, 'integral', 101 ,$signin['id'] , 6,'补签15天奖励');
            }
            //如果连续签到天数是30的倍数，则发放奖励
            if($continue_days % 30 == 0 && $signin_reward_log30 == 0){
                User::changeInc($user['id'],$signin_30_amount,'balance',100,$signin['id'],4,'补签30天奖励');
                User::changeInc($user['id'],$signin_30_jifen,'integral',100,$signin['id'],6,'补签30天奖励');
            }
            // 初始化连续签到天数
            $continueDays = $continue_days;
            $lastDate = strtotime($signin_date);
            // 遍历签到记录，重新计算连续签到天数
            foreach ($signinRecords as $record) {
                $currentDate = strtotime($record['signin_date']);
                if ($currentDate - $lastDate === 86400) {
                    // 如果当前日期和上一个日期相差一天，则连续签到天数加1
                    $continueDays++;
                } else {
                    // 如果不连续，则重新开始计算
                    $continueDays = 1;
                }

                // 更新数据库中的连续签到天数
                UserSignin::where('id', $record['id'])
                    ->update(['continue_days' => $continueDays]);
                //如果是第15天，则发放奖励
                
                if($continueDays % 15 == 0 && $signin_reward_log15 == 0){
                    User::changeInc($user['id'], $signin_15_amount, 'balance', 101 ,$record['id'] , 4,'补签15天奖励');
                    User::changeInc($user['id'], $signin_15_jifen, 'integral', 101 ,$record['id'] , 6,'补签15天奖励');
                }
                //如果是第30天，则发放奖励
                if($continueDays % 30 == 0 && $signin_reward_log30 == 0){
                    User::changeInc($user['id'],$signin_30_amount,'balance',100,$record['id'],4,'补签30天奖励');
                    User::changeInc($user['id'],$signin_30_jifen,'integral',100,$record['id'],6,'补签30天奖励');
                }
                $lastDate = $currentDate;
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out($date);
    }

    public function userSigninList()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->find();
        $data = UserSignin::where('user_id', $user['id'])->order('signin_date', 'desc')->select()->each(function ($item) {
            $item['is_buqian'] = $item['signin_back'] == 1 ? "补签" : '签到';
        })->toArray();
        return out($data);
    }

    //开红包
    public function kaiHongBao()
    {
        $user = $this->user;

        //判断今天是否签到
        $signin_date = date('Y-m-d');
        $todaySigninCount = UserSignin::where('user_id', $user['id'])->where('signin_date', $signin_date)->lock(true)->count();
        if ($todaySigninCount == 0) {
            return out(null, 10001, '您还没签到');
        }

        //判断今天是否开过红包
        $prizeCount = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('signin_date',$signin_date)->count();
        if ($prizeCount){
            //return out(null, 10001, '已开启红包，请明日再来');
        }

        //集字赢大礼 start
        //签到（lianxu qiandao ）
        $last_sign = UserSignin::where('user_id', $user['id'])->order('signin_date', 'desc')->find();
        //内定
        $luckyCount = HongbaoUserSetting::where('user_id',$user['id'])->where('status',0)->count();
        if ($luckyCount >= 1){
            $prizeData = $this->luckyUser($user['id'], $user['created_at'], $last_sign['id'], $last_sign['continue_days'], $user['phone']);
        } else {
            $prizeData = $this->jiziyingdali($user['id'], $user['created_at'], $last_sign['id'], $last_sign['continue_days'] ,$user['phone']);
        }
        //集字赢大礼 end

        return out($prizeData);
    }

    //集字赢大礼
    // $userId 用户ID
    // $userCreatedAt 用户创建时间
    // $signId  签到ID
    // $continueDays 连续签到次数
    public function jiziyingdali($userId, $userCreatedAt, $signId, $continueDays, $phone)
    {
        //大于这个时间为新人  小于这个时间是老人
        $signin_date = date('Y-m-d');
        $ymdhis = '2025-01-06 00:00:00';
        //缴纳金判断
        $count = Order5::where('user_id',$userId)->count();

        //新用户完成开具资金来源证明，新用户必得100元蛇年红包
        if (strtotime($userCreatedAt) >= strtotime($ymdhis) && $count == 1){
            $money = "100";
            //第一次签到判断
            $newFirstCount = UserBalanceLog::where('user_id',$userId)->where('log_type',13)->where('type',106)->count();
            if ($newFirstCount == 0){
                User::changeInc($userId,100,'shenianhongbao',106, $signId,14,'蛇年红包');
                return (string)$money;
            }
            //开具资金来源证明不管新老用户连续签到十五天必得蛇年福运金100元
            if ($continueDays % 15 == 0){
                User::changeInc($userId,100,'shenianhongbao',30, $signId,14,'蛇年红包');
                return (string)$money;
            }
        }

        //未完成开具资金来源证明老用户签到只会中字 连续签到3天中20元蛇年红包 连续签到5天中10元蛇年红包
        if (strtotime($userCreatedAt) < strtotime($ymdhis) && $count == 0){
            if ($continueDays % 3 == 0){
                $money = mt_rand(1,5);
                User::changeInc($userId,$money,'shenianhongbao',30, $signId,14,'蛇年红包');
                return (string)$money;
            }

            if ($continueDays % 10 == 0){
                $money = mt_rand(5,10);
                User::changeInc($userId,$money,'shenianhongbao',30, $signId,14,'蛇年红包');
                return (string)$money;
            }
        }

        //老用户开具完成资金来源证明第一次签到即可中50元，连续签到7天中20元，连续签到10天中20元，连续签到15天中20元
        if (strtotime($userCreatedAt) < strtotime($ymdhis) && $count == 1){
            //第一次签到判断
            $oldFirstCount = UserBalanceLog::where('user_id',$userId)->where('log_type',14)->where('type',107)->count();
            if ($oldFirstCount == 0){
                User::changeInc($userId,50,'shenianhongbao',107, $signId,14,'蛇年红包');
                return 50;
            }
            if ($continueDays % 7 == 0){
                $qian7 = mt_rand(1,5);
                User::changeInc($userId,$qian7,'shenianhongbao',107, $signId,14,'蛇年红包');
                return (string)$qian7;
            }

            if ($continueDays % 10 == 0){
                $qian10 = mt_rand(5,10);
                User::changeInc($userId,$qian10,'shenianhongbao',107, $signId,14,'蛇年红包');
                return (string)$qian10;
            }

            if ($continueDays % 15 == 0){
                $qian15 = mt_rand(10,15);
                User::changeInc($userId,$qian15,'shenianhongbao',107, $signId,14,'蛇年红包');
                //开具资金来源证明不管新老用户连续签到十五天必得蛇年福运金100元
                User::changeInc($userId,100,'shenianhongbao',309, $signId,14,'蛇年红包');
                return (string)$qian15;
            }
        }

        //新用户未完成开具资金证明签到首次必中20元蛇年福运金，未完成一直不满100元
        if (strtotime($userCreatedAt) >= strtotime($ymdhis) && $count == 0){
            //第一次签到判断
            $oldFirstCount = UserBalanceLog::where('user_id',$userId)->where('log_type',14)->where('type',108)->count();
            if ($oldFirstCount == 0){
                User::changeInc($userId,20,'shenianhongbao',108, $signId,14,'蛇年红包');
                return "20";
            }
        }

        //随机奖品
        $result = array();
        $proArr = HongbaoSigninPrize::order('id', 'asc')->select()->toArray();
        foreach ($proArr as $key => $val) {
            $arr[$key] = $val['v'];
        }
        $proSum = array_sum($arr);
        asort($arr);
        foreach ($arr as $k => $v) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $v) {
                $result = $proArr[$k];
                break;
            } else {
                $proSum -= $v;
            }
        }

        //mp_hongbao_signin_prize_log
        HongbaoSigninPrizeLog::create([
            'user_id' => $userId,
            'prize_id' => $result['id'],
            'phone' => $phone,
            'prize_name' => $result['name'],
            'signin_date' => $signin_date,
            'signin_days' => $continueDays,
        ]);

        return $result['name'];
    }

    //内定人
    public function luckyUser($userId, $userCreatedAt, $signId, $continueDays, $phone)
    {
        $data = HongbaoUserSetting::where('user_id',$userId)->where('status',0)->order('id asc')->limit(1)->find();
        HongbaoSigninPrizeLog::create([
            'user_id' => $userId,
            'prize_id' => $data['prize_id'],
            'phone' => $phone,
            'prize_name' => $data['prize_name'],
            'signin_date' => date('Y-m-d'),
            'signin_days' => $continueDays,
        ]);

        HongbaoUserSetting::where('id',$data['id'])->update(['status' => 1]);
        return $data['name'];
    }

    public function getContinueSign()
    {
        $user = $this->user;
        $last_date = date('Y-m-d', strtotime("-1 days"));
        $today = date('Y-m-d');
        $lastsign = UserSignin::where('signin_date','in',[$last_date,$today])
            ->where('user_id',$user['id'])
            ->order('signin_date','desc')->select();
        $contue = 0;
        if($lastsign && !$lastsign->isEmpty()){
            $contue = $lastsign[0]['continue_days'];
        }
        //leiji
        $leiji = UserSignin::where('user_id',$user['id'])->count();
        $jDdays = $contue % 30;
        if($jDdays < 15 ){
            $surprises_days = 15-$jDdays;
        }else{
            $surprises_days = 30-$jDdays;
        }
        $date = ['lianxu'=>$contue,'leiji'=>$leiji,'surprises_days'=>$surprises_days];
        return out($date);
    }


    public function signinRecord()
    {
        $user = $this->user;
        $month = self::getMonth();
        $data = [];

        $list = UserSignin::where('user_id', $user['id'])->order('signin_date', 'desc')->select()->toArray();
        $day = array_column($list, 'signin_date');
        foreach ($month as $key => $value) {
            if(in_array($value, $day)){
                $data[] = [
                    'date' => $value,
                    'type' => 'normal'
                ];
            } else {
                $today = date('Y-m-d');
                if($value <= $today) {
                    $data[] = [
                        'date' => $value,
                        'type' => 'abnormal'
                    ];
                }
            }

        }
        $one = UserSignin::where('user_id', $user['id'])->order('signin_date', 'desc')->find();
        return out([
            'total_continue_days' => $one['continue_days'] ?? 0,
            'list' => $data
        ]);
    }

    public static function getMonth($time = '', $format='Y-m-d'){
        $time = $time != '' ? $time : time();
        //获取当前周几
        $week = date('d', $time);
        $date = [];
        for ($i=1; $i<= date('t', $time); $i++){
            $date[$i] = date($format ,strtotime( '+' . ($i-$week) .' days', $time));
        }
        return $date;
    }

    public function signinPrizeList()
    {
        $prizeList = TurntableSignPrize::order('id', 'asc')->select();
        return out($prizeList);
    }

    /**
     * 1 签到幸运大转盘（66,88,99,128,188数字人民币轮序）
     */
    public function turntableSign()
    {
        $user = $this->user;
        $clickRepeatName = 'turntable-sign-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $signin_date = date('Y-m-d');
        //是否签到
        $isSign = UserSignin::where('signin_date', $signin_date)->where('user_id', $user->id)->find();
        if ($isSign) {
            return out(null, 10001, '今日已签到');
        }

        //确定奖励
        $lastPrize = UserSignin::where('user_id', $user->id)->where('prize_id', '>', 0)->order('id', 'desc')->find();
        if (empty($lastPrize)) {
            $prize = TurntableSignPrize::order('id', 'asc')->find();
        } else {
            $prize = TurntableSignPrize::where('id', '>', $lastPrize['prize_id'])->order('id', 'asc')->find();
            if (empty($prize)) {
                $prize = TurntableSignPrize::order('id', 'asc')->find();
            }
        }
        
        $sigiinId = UserSignin::insertGetId([
            'user_id' => $user->id, 
            'prize_id' => $prize['id'],
            'reward' => $prize['name'],
            'signin_date' => $signin_date,
        ]);

        User::changeInc($user->id,$prize['name'],'digital_yuan_amount',34,$sigiinId,3, '签到数字人民币奖励','',1,'SR');

        $data = ['prize_id' => $prize['id'], 'name' => $prize['name']];
        return out($data);
    }

    /**
     * 签到、抽奖记录
     */
    // public function signinRecord()
    // {
    //     $user = $this->user;
    //     $time = date("Y-m")."-01";

    //     $list = UserSignin::where('user_id', $user['id'])->where("signin_date",'>=',$time)->order('id', 'desc')->select()->toArray();
    //     foreach ($list as &$item) {
    //         $item['day'] = date('d', strtotime($item['signin_date']));
    //     }
    //     return out([
    //         'total_signin_num' => count($list),
    //         'list' => $list
    //     ]);
    // }

    /**
     * 推荐幸运大转盘
     */
    public function turntableInvite()
    {
        $user = $this->user;
        $clickRepeatName = 'turntable-invite-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        //是否有抽奖机会
        $isSign = 0;
        if ($isSign <= 0) {
            return out(null, 10001, '无转盘机会');
        }

        //确定奖励
        $lastPrize = TurntableUserLog::where('user_id', $user->id)->where('type', 'invite')->order('id', 'desc')->find();
        if (empty($lastPrize)) {
            $prize = TurntableInvitePrize::find(1);
        } else {
            $prize = TurntableInvitePrize::find($lastPrize['prize_id'] + 1);
            if (empty($prize)) {
                $prize = TurntableInvitePrize::find(1);
            }
        }
        
        TurntableUserLog::insert([
            'user_id' => $user->id, 
            'prize_id' => $prize['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'invite',
        ]);

        $data = ['prize_id' => $prize['id'], 'name' => $prize['name']];
        return out($data);
    }

    /**
     * 推荐五人幸运大转盘
     */
    public function turntableInvite5()
    {
        $user = $this->user;
        $clickRepeatName = 'turntable-ivnite5-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        //是否有抽奖机会
        $isSign = 0;
        if ($isSign <= 0) {
            return out(null, 10001, '无转盘机会');
        }

        //确定奖励
        $lastPrize = TurntableUserLog::where('user_id', $user->id)->where('type', 'invite5')->order('id', 'desc')->find();
        if (empty($lastPrize)) {
            $prize = TurntableInvite5Prize::find(1);
        } else {
            $prize = TurntableInvite5Prize::find($lastPrize['prize_id'] + 1);
            if (empty($prize)) {
                $prize = TurntableInvite5Prize::find(1);
            }
        }
        
        TurntableUserLog::insert([
            'user_id' => $user->id, 
            'prize_id' => $prize['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'invite5',
        ]);

        $data = ['prize_id' => $prize['id'], 'name' => $prize['name']];
        return out($data);
    }

    //抽奖
    // public function getRand($proArr) { 
    //     $result = ''; 
    //     $proSum = array_sum($proArr);  
    //     foreach ($proArr as $key => $proCur) { 
    //         $randNum = mt_rand(1, $proSum); 
    //         if ($randNum <= $proCur) { 
    //             $result = $key; 
    //             break; 
    //         } else { 
    //             $proSum -= $proCur; 
    //         }    
    //     } 
    //     unset ($proArr); 
    //     return $result; 
    // }

    // /**
    //  * 可抽奖次数
    //  */
    // public function smashTimes()
    // {
    //     return out($this->egg());
    // }

    public function getWenZi()
    {
        $user = $this->user;

//        $sql = "SELECT
//                    a.prize_id,
//                    b.img_url
//                FROM
//                    mp_hongbao_signin_prize_log a
//                    LEFT JOIN mp_hongbao_signin_prize b ON a.prize_id = b.id
//                WHERE
//                    a.user_id = ".$user['id']."
//                    AND a.prize_id IN (1,2,3,4,6,7,8,17)
//                GROUP BY
//                    prize_id";
//        $data = Db::query($sql);
//        return out($data);
        $count1 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',1)->count();
        $count2 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',2)->count();
        $count3 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',3)->count();
        $count4 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',4)->count();

        $count6 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',6)->count();
        $count7 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',7)->count();
        $count8 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',8)->count();
        $count17 = HongbaoSigninPrizeLog::where('user_id',$user['id'])->where('prize_id',17)->count();

        $arr = [
            '1' => $count1,
            '2' => $count2,
            '3' => $count3,
            '4' => $count4,
            '6' => $count6,
            '7' => $count7,
            '8' => $count8,
            '17' => $count17,
        ];
        return out($arr);

    }

    public function getHuangJinReward(){
        $user = $this->user;
        // 检查本月是否已经领取过该奖励
         $huangjin_reward = UserSigninRewardLog::where('user_id', $user['id'])
         ->where('reward_days', 30)
         ->where('status', 1)
         ->find()->toArray();
        return out($huangjin_reward);
    }

    /**
     * 领取签到奖励
     * 参数：type=1（连续7天），type=2（连续15天），type=3（连续30天）
     * @return \think\response\Json
     */
    public function receiveReward()
    {
        $user = $this->user;
        $userId = $user['id'];
        $req = request()->param();
        
        // 验证参数
        if (!isset($req['type']) || !in_array($req['type'], [1, 2, 3])) {
            return out(null, 10001, '参数错误：type必须为1、2或3（1-连续7天，2-连续15天，3-连续30天）');
        }
        
        $type = intval($req['type']);
        $rewardDays = UserSigninRewardLog::getRewardDaysByType($type);
        
        if (!$rewardDays) {
            return out(null, 10001, '参数错误：无效的type值');
        }

         // 检查本月是否已经领取过该奖励
         $huangjin_reward = UserSigninRewardLog::where('user_id', $userId)
         ->where('reward_days', 30)
         ->where('status', 1)
         ->find();

         if($huangjin_reward){
            return out(null, 10001, '已完成领取黄金奖励');
         }
        

        // 获取当前年月
        $rewardYearMonth = date('Y-m');
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        
        // 查询用户本月的最大连续签到天数
        $maxContinueDays = UserSignin::where('user_id', $userId)
            ->where('signin_date', '>=', $monthStart)
            ->where('signin_date', '<=', $monthEnd)
            ->max('continue_days') ?: 0;
        
        // 检查本月连续签到天数是否达到要求
        if ($maxContinueDays < $rewardDays) {
            return out(null, 10001, '您本月连续签到天数不足' . $rewardDays . '天，当前最大连续签到天数为' . $maxContinueDays . '天，无法领取该奖励');
        }
        
        // 检查本月是否已经领取过该奖励
        $exists = UserSigninRewardLog::where('user_id', $userId)
            ->where('reward_days', $rewardDays)
            ->where('reward_year_month', $rewardYearMonth)
            ->where('status', 1)
            ->find();
        
        if ($exists) {
            return out(null, 10001, '您本月已经领取过' . UserSigninRewardLog::getRewardDaysText($rewardDays) . '的奖励了');
        }
        
        Db::startTrans();
        try {
            // 创建领取记录
            $rewardLog = UserSigninRewardLog::create([
                'user_id' => $userId,
                'reward_days' => $rewardDays,
                'reward_year_month' => $rewardYearMonth,
                'reward_amount' => 0.00,  // 可根据实际业务设置奖励金额
                'reward_jifen' => 0,      // 可根据实际业务设置奖励积分
                'reward_gold' => 0,       // 可根据实际业务设置奖励金币
                'status' => 1,
                'remark' => '用户领取' . UserSigninRewardLog::getRewardDaysText($rewardDays) . '签到奖励',
            ]);

            if($req['type'] == 1){
                User::changeInc($userId,1,'lottery_tickets',100,$rewardLog->id,9, '签到奖励','',1,'SR');
            }
            if($req['type'] == 2){
                User::changeInc($userId,1,'lucky_tickets',100,$rewardLog->id,19, '幸运奖卷','',1,'SR');
            }
            if($req['type'] == 3){
                User::changeInc($userId,100,'gold_wallet',100,$rewardLog->id,18, '签到奖励','',1,'SR');
            }
            Db::commit();
            
            return out([
                'id' => $rewardLog->id,
                'reward_days' => $rewardDays,
                'reward_days_text' => UserSigninRewardLog::getRewardDaysText($rewardDays),
                'reward_year_month' => $rewardYearMonth,
                'created_at' => $rewardLog->created_at,
            ], 200, '领取成功');
            
        } catch (Exception $e) {
            Db::rollback();
            return out(null, 10001, '领取失败：' . $e->getMessage());
        }
    }

    /**
     * 本月领取记录
     * @return \think\response\Json
     */
    public function monthRewardList()
    {
        $user = $this->user;
        $userId = $user['id'];
        $rewardYearMonth = date('Y-m');
        
        // 查询本月所有领取记录
        $list = UserSigninRewardLog::where('user_id', $userId)
            ->where('reward_year_month', $rewardYearMonth)
            ->where('status', 1)
            ->order('reward_days', 'asc')
            ->order('created_at', 'desc')
            ->select();
        
        // 格式化数据
        $data = [];
        foreach ($list as $item) {
            $data[] = [
                'id' => $item->id,
                'reward_days' => $item->reward_days,
                'reward_days_text' => UserSigninRewardLog::getRewardDaysText($item->reward_days),
                'reward_year_month' => $item->reward_year_month,
                'reward_amount' => $item->reward_amount,
                'reward_jifen' => $item->reward_jifen,
                'reward_gold' => $item->reward_gold,
                'status' => $item->status,
                'remark' => $item->remark,
                'created_at' => $item->created_at,
            ];
        }
        
        // 统计本月已领取的奖励类型
        $receivedTypes = [];
        foreach ($list as $item) {
            $type = array_search($item->reward_days, UserSigninRewardLog::$rewardDaysMap);
            if ($type) {
                $receivedTypes[] = $type;
            }
        }
        
        return out([
            'reward_year_month' => $rewardYearMonth,
            'list' => $data,
            'total' => count($data),
            'received_types' => $receivedTypes,  // 已领取的类型：[1,2,3]
        ], 200, '查询成功');
    }
}
<?php

namespace app\api\controller;

use app\model\AssetOrder;
use app\model\User;
use app\model\UserReceive;
use app\model\UserSignin;
use Exception;
use think\facade\Db;
use think\model\relation\OneToOne;
use think\facade\Request;
class SigninController extends AuthController
{
    public function userSignin()
    {
        //return out(null, 10001, '暂未开放');
        
        
        // 每天签到时间为8：00-20：00 早上8点到晚上21点
/*         $timeNum = (int)date('Hi');
        if ($timeNum < 800 || $timeNum > 2100) {
            return out(null, 10001, '签到时间为早上8:00到晚上21:00');
        } */
        // $arr =config('map.noDomainArr');
        // $host = request()->host();
        // if(in_array($host,$arr)){
        //     return out(null, 10001, '请联系客服下载最新app进行签到');
        // }
        // if(!domainCheck()){
        //     return out(null, 10001, '请联系客服下载最新app进行签到');
        // }
        $user = $this->user;
        $user = User::where('id', $user['id'])->find();
        $signin_date = date('Y-m-d');
        if($user['level'] == 0){
            return out(null, 10001, '共富等级一级才有奖励');
        }
        $level_config = \app\model\LevelConfig::where('level', $user['level'])->find();
        return out($level_config);
        if(!$level_config){
            return out(null, 10001, '您的等级有误');
        }

        Db::startTrans();
        try {
            if (UserSignin::where('user_id', $user['id'])->where('signin_date', $signin_date)->lock(true)->count()) {
                return out(null, 10001, '您今天已经签到了');
            }


            // $oneDate = date('Y-m')."-01";
            // $signDates = UserSignin::where('user_id',$user['id'])->where('signin_date','>',$oneDate)->order('signin_date','desc')->select();
            // $signNum=1;
            // $dates = [];
            // foreach($signDates as $date){
            //     $dates[$date['signin_date']] = $date; 
            // }

            // $yesterday = date("Y-m-d");
            // foreach($signDates as $date){
            //    $yesterday = date('Y-m-d',strtotime("-1 day",strtotime($yesterday)));
            //    if(isset($dates[$yesterday])){
            //          $signNum++;
            //    }else{
            //             break;
            //    }   
            // }

            $signin = UserSignin::create([
                'user_id' => $user['id'],
                'signin_date' => $signin_date,
            ]);
            // 添加签到奖励积分
            //User::changeBalance($user['id'], dbconfig('signin_integral'), 17, $signin['id'], 2);
            // 签到奖励数码货币
            //User::changeBalance($user['id'], dbconfig('signin_digital_yuan'), 17, $signin['id'], 3);

            User::changeInc($user['id'],200,'digital_yuan_amount',17,$signin['id'],3,'',0,1,'QD');
            User::changeInc($user['id'],$level_config['cash_reward_amount'],'signin_balance',17,$signin['id'],3,'',0,1,'QD');

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    /**
     * 首先看恢复资产用户选择的先富后富
     * 没有恢复资产的用户，看是否充值了1500
     */
    public function dayReceive(){
        $user = $this->user;
        $user = User::where('id', $user['id'])->find();
        $signin_date = date('Y-m-d');
        if($user['level'] == 0){
            return out(null, 10001, '共富等级一级才有奖励');
        }
        $is_rich=0;
        $assetOrder = AssetOrder::where('user_id',$user['id'])->where('status',2)->find();
        if($assetOrder){
            $is_rich = $assetOrder['rich'] ==1 ? 1 : 0;
        }

        if($is_rich == 0 && !$assetOrder){
            if($user['invest_amount']>=1500){
                $is_rich = 1;
            }
        }

        Db::startTrans();
        try {
            if (UserReceive::where('user_id', $user['id'])->where('signin_date', $signin_date)->lock(true)->count()) {
                return out(null, 10001, '您今天已经领取了');
            }
            $signin = UserReceive::create([
                'user_id' => $user['id'],
                'signin_date' => $signin_date,
            ]);
            $amount = $is_rich == 1 ? 14 : 1;
            User::changeInc($user['id'],$amount,'signin_balance',17,$signin['id'],3,'',0,1,'QD');
            Db::commit();
           
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
            return out(null,200,$e->getMessage());
        }
        return out();
    }

    public function signinRecord()
    {
        $user = $this->user;
        $time = date("Y-m")."-01";

        $list = UserSignin::where('user_id', $user['id'])->where("signin_date",'>=',$time)->order('id', 'desc')->select()->toArray();
        foreach ($list as &$item) {
            $item['day'] = date('d', strtotime($item['signin_date']));
        }
        return out([
            'total_signin_num' => count($list),
            'list' => $list
        ]);
    }
}

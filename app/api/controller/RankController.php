<?php

namespace app\api\controller;

use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use app\model\TeamGloryLog;
use app\model\TeamGlorySetting;
use think\facade\Db;
use think\facade\Cache;

class RankController extends AuthController
{

    public function yuce()
    {
        $req = $this->validate(request(), [
            //'year|年份' => 'require',
            'mode|模式' => 'require',
        ]);
        if($req['mode'] == 1) {
            $data = config('map.diyuce');
        } elseif ($req['mode'] == 2) {
            $data = config('map.zhongyuce');
        } elseif ($req['mode'] == 3) {
            $data = config('map.gaoyuce');
        }
        
        return out($data);
    }


    public function rankList()
    {

        $relation = UserRelation::rankList();
        return out($relation);
    }


    public function teamRankList2()
    {
        $relation = UserRelation::alias('r')->field(['count(r.sub_user_id) as team_num', 'r.user_id'])->join('user u', 'u.id = r.sub_user_id')->where('r.is_active',1)->whereTime('r.created_at','today')->group('r.user_id')->order('team_num', 'desc')->limit(10)->select()->toArray();
        $users = [];
        $rankData = Db::table('mp_active_rank')->field('phone,num as team_num')->select();

        if (!empty($relation)) {
            $relation = array_column($relation, 'team_num', 'user_id');

            $user_ids = array_keys($relation);
            array_unshift($user_ids, 'id');
            $str = 'field('.implode(',', $user_ids).')';

            $users = User::field('id,phone')->whereIn('id', $user_ids)->where('status', 1)->order(Db::raw($str))->select()->toArray();
            foreach ($users as $k => $v) {
                $users[$k]['phone'] = substr_replace($v['phone'],'****', 3, 4);
                $users[$k]['team_num'] = $relation[$v['id']];
            }
        }
        foreach ($rankData as $k => $v) {
            $v['id'] =0;
            $v['phone'] = substr_replace($v['phone'],'****', 3, 4);
            $users[] = $v;
        }
        $column = array_column($users,'team_num');
        array_multisort($column,SORT_DESC,$users);
        $data = array_slice($users,0,10);
        return out($data);
    }

    public function teamGloryInfo(){

        $user = $this->user;
        $user_id = $user['id'];
        if(!Cache::get('teamGlorySetting')){
            Cache::set('teamGlorySetting',TeamGlorySetting::select()->order('level','asc')->toArray(),300);
        }
        $teamGlorySetting = Cache::get('teamGlorySetting');

        Db::startTrans();
        try {
            user::upLevel($user_id);

            //获取团队人数，实名人数
            $zong_total = UserRelation::alias('ur')->leftJoin('mp_user u','ur.sub_user_id=u.id')->where('ur.user_id', $user_id)->where('u.shiming_status',1)->count(); //实名团队人


            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        $user = User::where('id',$user_id)->field('invest_amount,level,is_active')->find();
        $nexLevel = $user['level']+1;
        $level_get_ids = TeamGloryLog::where("user_id",$user_id)->column('vip_level');
        
        foreach ($teamGlorySetting as $k => $v) {
            if($nexLevel < $v['level']){
                $level_get_status['vip'.$v['level']] = 1;   //不可领取
            }elseif($nexLevel == $v['level']){
                $nex = $v['register_num'];
                $level_get_status['vip'.$v['level']] = 1;   //不可领取
            }elseif($nexLevel > $v['level']){
                if(in_array($v['level'],$level_get_ids)){
                    $level_get_status['vip'.$v['level']] = 3;   //已领取
                }else{
                    $level_get_status['vip'.$v['level']] = 2;   //可领取
                }
            }
            $level_reward['vip'.$v['level']] = $v['reword_money'];
            $level_discount['vip'.$v['level']] = $v['discount'];
            $levelPoints['vip'.$v['level']] = $v['register_num'];
        }

        $data['zong_total'] = $zong_total;
        $data['user_level'] = $user['level'];
        $data['tonext'] = $nex-$zong_total;
        $data['level_reward'] = $level_reward;
        $data['level_discount'] = $level_discount;
        $data['level_register_point_map'] = $levelPoints;
        $data['level_get_status'] = $level_get_status;

        return out($data);

    }

    /**
     * @return \think\response\Json
     * 团队荣耀领取
     */
    public function teamGlory(){
        $req = $this->validate(request(), [
            'vip' => 'require|number|between:1,10',
        ]);
        $user = $this->user;
        $vip = $req['vip'];

        if(!Cache::get('teamGlorySetting')){
            Cache::set('teamGlorySetting',TeamGlorySetting::select()->order('level','asc')->toArray(),300);
        }
        $teamGlorySetting = Cache::get('teamGlorySetting');
        $level_reward = [];
        $level_discount = [];
        foreach ($teamGlorySetting as $k => $v){
            $level_reward[$v['level']] = $v['reword_money'];
            $level_discount[$v['level']] = $v['discount'];
        }

        Db::startTrans();
        try {
            user::upLevel($user['id']);
            $userVip = User::where('id',$user['id'])->field('level')->find()->toArray();
            if($vip > $userVip['level']){
                return out(null, 10001, '用户未达到该等级');
            }

           $gloryRecord =  TeamGloryLog::where("user_id",$user['id'])
               ->where('vip_level',$vip)->count();

            if($gloryRecord > 0){
                return out(null, 10001, '用户已领取');
            }
            $data['user_id'] = $user['id'];
            $data['vip_level'] = $vip;
            $data['get_discount'] = $level_discount[$vip];
            $data['get_money'] = $level_reward[$vip];
            $data['creat_time'] = date('Y-m-d H:i:s',time());
            $TeamGloryLog = TeamGloryLog::create($data);

            User::changeInc($user['id'],$level_reward[$vip],'team_bonus_balance',8,$TeamGloryLog['id'],2,'团队荣誉奖');

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();

    }

    public function teamGloryList(){
        $user = $this->user;
        $list = TeamGloryLog::where("user_id",$user['id'])->select()->toArray();
       
        return out([
            'list' => $list,
        ]);
    }
}

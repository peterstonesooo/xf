<?php

namespace app\common\command;

use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\User;
use app\model\UserRelation;
use app\model\UserSignin;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\Capital;
use Exception;
use think\facade\Log;

class CheckTeamLeader extends Command
{
    protected function configure()
    {
        $this->setName('checkTeamLeader')->setDescription('团队长每月工资发放，每天的0点1分执行');
    }

    protected function execute(Input $input, Output $output)
    {   

        if(date('j') == 15) {

        }
        $data = UserRelation::alias('r')->leftJoin('user u', 'u.id = r.sub_user_id')
            ->where('u.is_active', 1)
            ->where('r.level', 1)
            ->field('r.user_id,count(*) as count')
            ->group('r.user_id')
            ->having('count(count)>=30')
            ->select();
        if(count($data) > 0) {
            Db::startTrans();
            try{
                // foreach ($data as $item){
                //     $this->bonus($item);
                // }
                foreach ($data as $item){
                    $list[] = $this->bonus($item);
                }
                unset($list[0]);
                unset($list[1]);
                create_excel_file($list, [
                    'id' => '序号',
                    'name'=>'姓名',  
                    'phone' => '手机号',
                    'amount' => '工资',
                ], '工资' . date('YmdHis'));
                  Db::Commit();
            }catch(Exception $e){
                   Db::rollback();
                
                Log::error('分红收益异常：'.$e->getMessage().'--'.$e->getLine());
                throw $e;
            }
        }


    }

    public function bonus($item){
        if($item['user_id'] == 1 || $item['user_id'] == 2) {
            return true;
        }
        $res = UserRelation::alias('r')->leftJoin('user u', 'u.id = r.sub_user_id')
            ->where('r.user_id', $item['user_id'])->where('u.is_active', 1)->where('r.level', 1)
            ->field('u.active_time')
            ->order('u.active_time', 'asc')->limit(29, 1)->select();


        $Date_1=date("Y-m-d", $res[0]['active_time']);
        $Date_2=date("Y-m-d 23:59:59");
        $dddd=strtotime($Date_1);
        if($dddd >= 1715702400) {
            $d1 = $dddd + 86400;
        } else {
            $d1 = 1715702400;
        }
        $d2=strtotime("2024-09-15 23:59:59");
        $days=round(($d2-$d1)/3600/24);
        if($days >= 31) {
            if($item['count'] >= 30 && $item['count'] < 60) {
                $amount = 1200;
            } elseif ($item['count'] >= 60 && $item['count'] < 150) {
                $amount = 2500;
            } elseif ($item['count'] >= 150 && $item['count'] < 300) {
                $amount = 6000;
            } elseif ($item['count'] >= 300 && $item['count'] < 600) {
                $amount = 10000;
            } elseif ($item['count'] >= 600 && $item['count'] < 1500) {
                $amount = 25000;
            } elseif ($item['count'] >= 1500) {
                $amount = 50000;
            }
        } else {
            if($item['count'] >= 30 && $item['count'] < 60) {
                $amount = 1200;
            } elseif ($item['count'] >= 60 && $item['count'] < 150) {
                $amount = 2500;
            } elseif ($item['count'] >= 150 && $item['count'] < 300) {
                $amount = 6000;
            } elseif ($item['count'] >= 300 && $item['count'] < 600) {
                $amount = 10000;
            } elseif ($item['count'] >= 600 && $item['count'] < 1500) {
                $amount = 25000;
            } elseif ($item['count'] >= 1500) {
                $amount = 50000;
            }
            $mult = bcdiv($days, 31, 8);
            $amount = round($mult*$amount);
        }
        $u = User::where('id', $item['user_id'])->find();
        $aa = [
            'id' => $item['user_id'],
            'name' => $u['realname'] ?? '',
            'phone' => $u['phone'] ?? '',
            'amount' => $amount,
        ];
        return $aa;
       //User::changeInc($item['user_id'], $amount,'balance',46,1,1,'团队长月工资');
    }

}

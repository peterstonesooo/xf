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

class CheckTeam extends Command
{
    protected function configure()
    {
        $this->setName('checkTeam')->setDescription('团队长奖金发放，每天的0点1分执行');
    }

    protected function execute(Input $input, Output $output)
    {   
        $yesterdayStartTimestamp = strtotime("yesterday");
        $yesterdayEndTimestamp = strtotime("today");
        $data = UserRelation::alias('r')->leftJoin('user u', 'u.id = r.sub_user_id')
            ->where('u.active_time', '>=', $yesterdayStartTimestamp)
            ->where('u.active_time', '<', $yesterdayEndTimestamp)
            ->where('u.is_active', 1)
            ->where('r.level', 1)
            ->field('r.user_id,count(*) as count')
            ->group('r.user_id')
            ->having('count(count)>=3')
            ->select();
        if(count($data) > 0) {
            Db::startTrans();
            try{
                foreach ($data as $item){
                    $this->bonus($item);
                }
                Db::Commit();
            }catch(Exception $e){
                Db::rollback();
                
                Log::error('分红收益异常：'.$e->getMessage(),$e);
                throw $e;
            }
        }


    }

    public function bonus($item){
        if($item['user_id'] == 1 || $item['user_id'] == 2) {
            return true;
        }
        if($item['count'] >= 3 && $item['count'] < 8) {
            $amount = 58;
        } elseif ($item['count'] >= 8 && $item['count'] < 15) {
            $amount = 128;
        } elseif ($item['count'] >= 15 && $item['count'] < 28) {
            $amount = 268;
        } elseif ($item['count'] >= 28 && $item['count'] < 68) {
            $amount = 398;
        } elseif ($item['count'] >= 68) {
            $amount = 998;
        }
        User::changeInc($item['user_id'], $amount,'balance',46,1,1,'单日直推激活奖金');
    }

}

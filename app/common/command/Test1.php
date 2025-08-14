<?php

namespace app\common\command;


use app\model\Capital;
use app\model\Order5;
use app\model\RelationshipRewardLog;
use app\model\ShopOrder;
use app\model\User;
use app\model\UserBalanceLog;

use app\model\UserRelation;
use app\model\UserSignin;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;


class Test1 extends Command
{
    protected function configure()
    {
        $this->setName('Test1')->setDescription('宣传奖励补偿');
    }

    public function execute(Input $input, Output $output)
    {
        Db::startTrans();
        try {
//            $user = User::where('id',132727)->find();
//            // 给上3级团队奖（迁移至申领）
//            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
//            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
//            foreach ($relation as $v) {
//                $reward = round(dbconfig($map[$v['level']])/100*(17053.58-3410.00), 2);
//                if($reward > 0){
//                    User::changeInc($v['user_id'],$reward,'xuanchuan_balance',8,755,4,'宣传奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
//                    RelationshipRewardLog::insert([
//                        'uid' => $v['user_id'],
//                        'reward' => $reward,
//                        'son' => $user['id'],
//                        'son_lay' => $v['level'],
//                        'created_at' => date('Y-m-d H:i:s')
//                    ]);
//                }
//            }

            User::changeInc(219063, 500,'buzhujin',105,219063,9,'补助金',0,2,'BZJ');
            
//            $UserBalanceLog = UserBalanceLog::where('created_at','>=','2015-01-06 00:00:00')->where('type',105)->where('log_type',9)->where('change_balance',500)->select();
//            foreach ($UserBalanceLog as $item){
//                User::where('id',$item['user_id'])->dec('buzhujin',$item['change_balance'])->update();
//                UserBalanceLog::where('id',$item['id'])->delete();
//            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}

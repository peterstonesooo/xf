<?php

namespace app\common\command;

use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\console\input\Argument;
use Exception;
use think\facade\Log;

class ActiveRank extends Command
{
    protected function configure()
    {
        $this->setName('activeRank')
            ->addArgument('name', Argument::OPTIONAL, "clear清零")
            ->setDescription('激活用户日排行');
    }

    protected function execute(Input $input, Output $output)
    { 
        $name = trim($input->getArgument('name'));
        if($name=='clear'){
            $this->clear();
            echo "success";
            return;
        }else{
            $this->addNum();
            echo "success";
            return;
        }
    }

    protected function addNum(){
        $confs = config('map.active_rank_list');
        $users = Db::table('mp_active_rank')->select();
        $time = time();
        foreach($users as $key=>$user){
            $conf= $confs[$key];
            $max = round(60/$conf['min'],2);
            $min = round(60/$conf['max'],2);
            $minute = $this->randFloat($min,$max);
            //$num = rand($conf['min'],$conf['max']);
            //echo "{$user['id']} {$user['phone']} $min $max $minute \n";
            if($user['next_time']==0){
                Db::table('mp_active_rank')->where('id',$user['id'])->update(['next_time'=>$time+$minute*60,'update_time'=>$time]);
            }
            if($user['num']>=$conf['day_max']){
                continue;
            }
            if($user['next_time']<=$time){
                Db::table('mp_active_rank')->where('id',$user['id'])->update(['next_time'=>Db::raw('next_time+'.$minute*60),'num'=>Db::raw('num+1'),'update_time'=>$time]);
            }
        }
    }

    protected function init(){
        $users = User::where('is_agent',1)->order('id','asc')->select();
        foreach($users as $key=>$user){
            Db::table('mp_active_rank')->insert([
                'id'=>$key+1,
                'phone' => $user['phone'],
                'num' => 0,
            ]);
        }
    }

    protected function clear(){
        Db::table('mp_active_rank')->where('id','>',0)->update(['num'=>0,'next_time'=>0]);
    }

    protected function  randFloat($min = 0, $max = 1) {
        $rand = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return floatval(number_format($rand,2));
      }
}
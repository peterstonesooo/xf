<?php

namespace app\common\command;

use app\model\AssetOrder;
use app\model\Capital;
use app\model\EnsureOrder;
use think\facade\Cache;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class CheckBonus extends Command
{
    protected function configure()
    {
        $this->setName('checkBonus')->setDescription('项目收益 每分钟检测');
    }

    public function execute(Input $input, Output $output)
    {
        $output->writeln('==========' . date('Y-m-d H:i:s') . " start ==========");
        $cur_time = strtotime(date('Y-m-d 00:00:00'));
        $time = strtotime(date('Y-m-d 00:00:00'));
        $now = time();

        $data = Order::whereIn('project_group_id',[1])->where('status',2)->where('next_bonus_time', '<=', $cur_time)
            ->chunk(100, function($list) use ($output) {
                foreach ($list as $item) {
                    $this->bonus($item, $output);
                }
            });
        


        $output->writeln('==========' . date('Y-m-d H:i:s') . " done ==========");
    }

    // public function execute(Input $input, Output $output)
    // {   


    //     $cur_time = strtotime(date('Y-m-d 00:00:00'));
    //      $data2 = Order::whereIn('project_group_id',[1,2,3])->where('status',2)->where('next_bonus_time', '<=', $cur_time)
    //     ->chunk(100, function($list) {
    //         foreach ($list as $item) {
    //             $this->digiYuan($item);
    //         }
    //     }); 

    //     // 分红收益
    //     $data = Order::whereIn('project_group_id',[1,2,3])->where('status',2)->where('end_time', '<=', $cur_time)
    //     ->chunk(100, function($list) {
    //         foreach ($list as $item) {
    //             $this->bonus($item);
    //         }
    //     });

    //     //456期项目
    //     $data = Order::whereIn('project_group_id',[4,6])->where('status',2)->where('end_time', '<=', $cur_time)
    //     ->chunk(100, function($list) {
    //         foreach ($list as $item) {
    //             $this->bonus4($item);
    //         }
    //     });
    //     //二期新项目结束之后每月分红
    //     $this->secondBonus();
    //     //$this->widthdrawAudit();
    //     return true;
    // }


    public function bonus($order, $output){
            Db::startTrans();
            try{
                $amount = $order['daily_bonus_ratio'];
                User::changeInc($order['user_id'], $amount,'digit_balance',53,$order['id'],5,$order['project_name'].'每日收益');
                $next_bonus_time = strtotime(date('Y-m-d 00:00:00', strtotime('+ 1day')));
                $gain_bonus = bcadd($order['gain_bonus'],$amount,2);
                Order::where('id',$order->id)->update(['next_bonus_time'=>$next_bonus_time, 'gain_bonus' => $gain_bonus]);
                $cur_time = strtotime(date('Y-m-d 00:00:00'));
                if($order['end_time'] <= $cur_time) {
                    //Order::where('id',$order->id)->update(['status'=>4]);
                }
                Db::Commit();
            }catch(Exception $e){
                Db::rollback();
                
                Log::error('分红收益异常：'.$e->getMessage(),$e);
                throw $e;
            }
    }
    

    public function bonusGroup3($order, $output){
        Db::startTrans();
        try{

            //结束
            Order::where('id', $order->id)->update(['status' => 2]);
            //生育补贴
            User::changeInc($order['user_id'],$order['shengyu'],'yixiaoduizijin',85,$order['id'],8);

            Db::Commit();

            $output->writeln("【{$order['id']}】完成");
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('分红收益异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }

    public function bonusGroup4($order, $output){
        Db::startTrans();
        try{

            //结束
            Order::where('id', $order->id)->update(['status' => 2]);
            //生育补贴
            if ($order['created_at'] >= '2024-11-13 00:00:00') {
                User::changeInc($order['user_id'],bcmul($order['shengyu'], 2, 2),'yixiaoduizijin',85,$order['id'],7);
                User::changeInc($order['user_id'],bcmul($order['shengyu_butie'], 2, 2),'yixiaoduizijin',87,$order['id'],7);
            } else {
                User::changeInc($order['user_id'],$order['shengyu'],'yixiaoduizijin',85,$order['id'],7);
                User::changeInc($order['user_id'],$order['shengyu_butie'],'yixiaoduizijin',87,$order['id'],7);
            }

            Db::Commit();

            $output->writeln("【{$order['id']}】完成");
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('分红收益异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }

    public function bonusGroup5($order, $output){
        Db::startTrans();
        try{

            //结束
            Order::where('id', $order->id)->update(['status' => 2]);
            User::changeInc($order['user_id'], $order['buzhujin'], 'buzhujin', 88, $order['id'], 9);
            User::changeInc($order['user_id'], $order['shouyijin'], 'shouyijin', 89, $order['id'], 10);

            Db::Commit();

            $output->writeln("【{$order['id']}】完成");
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('分红收益异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }

    public function bonusGroup5Ext($order, $output){
        Db::startTrans();
        try{
            if (Order::where('user_id', $order['user_id'])->where('project_group_id', 5)->count() >= 2) {
                $sum = 0;
                $historyOrder = Order::where('user_id', $order['user_id'])->where('project_group_id', '<=', 5)->select()->toArray();
                foreach ($historyOrder as $key => $value) {
                    $sum = bcadd($sum, $value['price'], 2);
                }
                User::changeInc($value['user_id'],$sum,'yixiaoduizijin',101,$value['id'],7);
            }
            Db::Commit();
            $output->writeln("【{$order['user_id']}】完成");
        }catch(Exception $e){
            Db::rollback();
            Log::error('异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }

    public function bonus4($order){
        Db::startTrans();
        try{
            $add = bcadd($order['gain_bonus'], $order['all_bonus'], 2);
            $money = bcsub($add, $order['checkingAmount'], 2);

            $user = User::where('id', $order['user_id'])->find();
            if($user['private_bank_open'] == 1) {
                User::changeInc($order['user_id'],$money,'private_bank_balance',53,$order['id'],1, '银联入账');
                User::where('id', $order['user_id'])->inc('all_digit_balance', $money)->update();
            } else {
                User::where('id', $order['user_id'])->inc('digit_balance', $money)->update();
                User::where('id', $order['user_id'])->inc('all_digit_balance', $money)->update();
            }
            
            // if(($money + $balance) >= 500000) {
            //     if($balance >= 500000) {
            //         User::where('id', $order['user_id'])->inc('digit_balance', $money)->update();
            //     } else {
            //         $ad = bcsub(500000, $balance, 2);
            //         User::changeInc($order['user_id'],$ad,'digit_balance',48,$order['id'],7, '下发E-CNY钱包');
            //         $cc = bcsub($money, $ad, 2);
            //         User::where('id', $order['user_id'])->inc('digit_balance', $cc)->update();
            //     }
            // } else {
            //     User::changeInc($order['user_id'],$money,'digit_balance',48,$order['id'],7,'下发E-CNY钱包');
            // }

            //User::changeInc($order['user_id'],$order['single_gift_digital_yuan'],'digital_yuan_amount',5,$order['id'],3);
            Order::where('id',$order->id)->update(['status'=>6]);

            Db::Commit();
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('分红收益异常：'.$e->getMessage(),$e);
            throw $e;
        }


    }

    public function bonus6($order){
        Db::startTrans();
        try{
            $amount = bcmul($order['buy_amount'], $order['daily_bonus_ratio'] / 100, 2);
            User::changeInc($order['user_id'], $amount,'private_bank_balance',62,$order['id'],1);
            $next_bonus_time = strtotime(date('Y-m-d 00:00:00', strtotime('+ 1day')));
            $gain_bonus = bcadd($order['gain_bonus'],$amount,2);
            Order::where('id',$order->id)->update(['next_bonus_time'=>$next_bonus_time, 'gain_bonus' => $gain_bonus]);
            $cur_time = strtotime(date('Y-m-d 00:00:00'));
            if($order['end_time'] <= $cur_time) {
                $last_year_income = bcmul($order['buy_amount'], $order['year_income'] / 100, 2);
                User::changeInc($order['user_id'], $last_year_income,'private_bank_balance',54,$order['id'],1);
                User::changeInc($order['user_id'], $order['buy_amount'],'private_bank_balance',52,$order['id'],1);
                Order::where('id',$order->id)->update(['status'=>4]);
            }
            Db::Commit();
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('分红收益异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }

    protected function digiYuan($order){
        $cur_time = strtotime(date('Y-m-d 00:00:00'));
        $user = User::where('id',$order->user_id)->where('status',1)->find();
        if(is_null($user)) {
            //用户不存在,禁用
            return;
        }
        
/*         if($order->end_time < $cur_time){
            //结束分红
            Order::where('id',$order->id)->update(['status'=>4]);
            return;
        } */
        $day=0;
        $passiveIncome = PassiveIncomeRecord::where('order_id',$order['id'])->where('user_id',$order['user_id'])->where('execute_day',date('Ymd'))->find();
        if(!empty($passiveIncome)){
            //已经分红

            return;
        }
        $passiveIncome = PassiveIncomeRecord::where('order_id',$order['id'])->where('user_id',$order['user_id'])->order('execute_day','desc')->find();
        if(!$passiveIncome){
            $day=0;
        }else if($passiveIncome['days']>=$order['period']){
            //已经分红完毕
            return;
        }else{
            $day=$passiveIncome['days'];
        }
        $day+=1;
        $amount = $order['single_gift_digital_yuan'];
        Db::startTrans();
        try {
            PassiveIncomeRecord::create([
                    'project_group_id'=>$order['project_group_id'],
                    'user_id' => $order['user_id'],
                    'order_id' => $order['id'],
                    'execute_day' => date('Ymd'),
                    'amount'=>$amount,
                    'days'=>$day,
                    'is_finish'=>1,
                    'status'=>3,
                    'type'=>1,
                ]); 
            $next_bonus_time = strtotime('+1 day', strtotime(date('Y-m-d H:i:s',$order['next_bonus_time'])));
            $gain_bonus = bcadd($order['gain_bonus'],$amount,2);
            Order::where('id', $order['id'])->update(['next_bonus_time'=>$next_bonus_time,'gain_bonus'=>$gain_bonus]);
            User::changeInc($order['user_id'],$amount,'digital_yuan_amount',5,$order['id'],3,'每日国务院津贴');
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return true;
    }
    
    protected function fixedMill($order)
    {
        $cur_time = strtotime(date('Y-m-d 00:00:00'));
        $user = User::where('id',$order->user_id)->where('status',1)->find();
        if(is_null($user)) {
            //用户不存在,禁用
            return;
        }
        
        if($order->end_time < $cur_time){
            //结束分红
            Order::where('id',$order->id)->update(['status'=>4]);
            return;
        }
        $max_day = PassiveIncomeRecord::where('order_id',$order['id'])->max('days');
        if($max_day >= 0){
            $max_day = $max_day + 1;
        }else{
            $max_day = 1;
        }
        $amount = bcmul($order['single_amount'],$order['daily_bonus_ratio']/100,2);
        $amount = bcmul($amount, $order['buy_num'],2);
        Db::startTrans();
        try {
            PassiveIncomeRecord::create([
                    'user_id' => $order['user_id'],
                    'order_id' => $order['id'],
                    'execute_day' => date('Ymd'),
                    'amount'=>$amount,
                    'days'=>$max_day,
                    'is_finish'=>1,
                    'status'=>3,
                ]); 
            if(empty($order['dividend_cycle'])){ 
                $dividend_cycle = '1 day'; 
            }else{
                $dividend_cycle = $order['dividend_cycle']; 
            }
            if(empty($order['next_bonus_time']) || $order['next_bonus_time'] == 0){ $order['next_bonus_time'] = $cur_time; }
            $next_bonus_time = strtotime('+'.$dividend_cycle, strtotime($order['next_bonus_time']));
            $gain_bonus = bcadd($order['gain_bonus'],$amount,2);
            Order::where('id', $order['id'])->update(['next_bonus_time'=>$next_bonus_time,'gain_bonus'=>$gain_bonus]);
            if($order->period <= $max_day){
                //结束分红
                Order::where('id',$order->id)->update(['status'=>4]);
            }
            if($order['settlement_method'] == 1)
                User::changeBalance($order['user_id'],$amount,6,$order['id'],3);
            else
                User::changeBalance($order['user_id'],$amount,6,$order['id']);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return true;
        

    }
}

<?php

namespace app\common\command;

use app\model\AssetOrder;
use app\model\Capital;
use app\model\EnsureOrder;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\ReleaseWithdrawal;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class CheckRelease extends Command
{
    protected function configure()
    {
        $this->setName('checkRelease')->setDescription('释放提现');
    }

    public function execute(Input $input, Output $output)
    {
        $cur_time = time();
        $time = strtotime(date('Y-m-d 00:00:00'));
        $data = ReleaseWithdrawal::where('status',3)->where('end_time', '<=', $cur_time)
        ->chunk(100, function($list) {
            foreach ($list as $item) {
                $this->bonus($item);
            }
        });

    }



    public function bonus($order){
        Db::startTrans();
        try{
            $user  = User::where('id', $order['user_id'])->find();
            if($user['private_bank_open'] && $user['private_release'] <= 0) {
                User::changeInc($order['user_id'], -$order['amount'],'private_bank_balance',59,$order['id'],1);
            }
            User::changeInc($order['user_id'], $order['amount'],'release_balance',60,$order['id'],1);
            ReleaseWithdrawal::where('id',$order->id)->update(['status'=>4]);
            Db::Commit();
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('分红收益异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }

}

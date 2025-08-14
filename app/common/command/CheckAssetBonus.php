<?php

namespace app\common\command;

use app\model\AssetOrder;
use app\model\Capital;
use app\model\EnsureOrder;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\User;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class CheckAssetBonus extends Command
{
    protected function configure()
    {
        $this->setName('checkAssetBonus')->setDescription('资产48小时恢复，每五分钟执行');
    }

    public function execute(Input $input, Output $output)
    {

       $data = AssetOrder::where('reward_status',0)->where('next_reward_time', '<=', time())
       ->chunk(100, function($list) {
          foreach ($list as $item) {
              $this->bonus_asset_reward($item);
          }
      });
    }


    public function bonus_asset_reward($order)
    {
        Db::startTrans();
        try{
            //User::changeInc($order['user_id'],$order['balance'],'digital_yuan_amount',27,$order['id'],3);
            User::changeInc($order['user_id'],$order['digital_yuan_amount'],'digital_yuan_amount',27,$order['id'],3,'',0,1,'JJ');
            $user = User::where('id', $order['user_id'])->find();
            // if($user['level'] < $order['level']) {
            //     User::where('id', $order['user_id'])->update(['level' => $order['level']]);
            // }
            //User::changeInc($order['user_id'],$order['poverty_subsidy_amount'],'poverty_subsidy_amount',27,$order['id'],3);
            AssetOrder::where('id',$order->id)->update(['reward_status'=>1]);
            Db::Commit();
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('资产恢复异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }
}

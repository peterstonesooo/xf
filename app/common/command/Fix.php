<?php

namespace app\common\command;

use app\model\AssetOrder;
use app\model\Capital;
use app\model\EnsureOrder;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\ShopOrder;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class Fix extends Command
{
    protected function configure()
    {
        $this->setName('fix')->setDescription('测试脚本');
    }

    public function execute(Input $input, Output $output)
    {
        ini_set ("memory_limit","-1");
        set_time_limit(0);
        
        $data = UserBalanceLog::where('type', '43')->where('relation_id', '<', 24)->where('created_at', '<', '2024-05-05 17:20:00')->select();
        Db::startTrans();
        try {
            foreach ($data as $key => $value) {
                //User::changeInc($value['id'],$value['amount'],'balance',99,$value['id'],2, $value['remark'],'',1,'IM');
                $user = User::where('id', $value['user_id'])->find();
                if($user) {
                    if ($user['digital_yuan_amount'] >= $value['change_balance']) {
                        User::where('id', $value['user_id'])->inc('digital_yuan_amount',-$value['change_balance'])->update();
                    } else {
                        User::where('id', $value['user_id'])->inc('digital_yuan_amount',-$user['digital_yuan_amount'])->update();
                        $q = $value['change_balance'] - $user['digital_yuan_amount'];
                        echo "[{$user['id']}]" . '欠' . '[{$q}]';
                    }
                    
                }
                UserBalanceLog::where('id', $value['id'])->delete();
            }
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            echo  $e->getMessage().$e->getLine();
            throw $e;
        }
    }

    


}

<?php

namespace app\common\command;

use app\model\Order;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;

use think\facade\Db;

use Exception;

class GaoJiYongHu extends Command
{
    protected function configure()
    {
        $this->setName('GaoJiYongHu')->setDescription('高级用户，债务基金');
    }

    public function execute(Input $input, Output $output)
    {
        $data = Order::where('project_name',"高级用户")->where('created_at', '>=', '2024-12-25 00:00:00')->select();
        foreach ($data as $item){
            Db::startTrans();
            try {
                //债务基金
                User::changeInc($item['user_id'],100000,'zhaiwujj',3,0,13,'高级用户25日补贴',0,1,'ZWJJ');
                Db::Commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }
    }

}

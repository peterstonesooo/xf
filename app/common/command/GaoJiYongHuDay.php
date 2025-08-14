<?php

namespace app\common\command;

use app\model\Order;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;

use think\facade\Db;

use Exception;

class GaoJiYongHuDay extends Command
{
    protected function configure()
    {
        $this->setName('GaoJiYongHuDay')->setDescription('高级用户，日补贴');
    }

    public function execute(Input $input, Output $output)
    {
        $data = Order::where('project_name',"高级用户")->select();
        foreach ($data as $item){
            Db::startTrans();
            try {
                //日补助
                User::changeInc($item['user_id'],20,'yu_e_bao_shouyi',3,0,12,'高级用户日补贴',0,1,'RBZ');
                Db::Commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }
    }

}

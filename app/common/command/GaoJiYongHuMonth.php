<?php

namespace app\common\command;

use app\model\Order;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;

use think\facade\Db;

use Exception;

class GaoJiYongHuMonth extends Command
{
    protected function configure()
    {
        $this->setName('GaoJiYongHuMonth')->setDescription('高级用户，月补贴');
    }

    public function execute(Input $input, Output $output)
    {
        $data = Order::where('project_name',"高级用户")->where('created_at','<=','2024-12-24 23:59:59')->select();
        foreach ($data as $item){
            Db::startTrans();
            try {
                //日期
                $time = date('Y-m-d H:i:s');
                User::changeInc1($item['user_id'],5000,'yixiaoduizijin',3,0,7,'高级用户月补贴',0,1,'YBZ',$time);
                Db::Commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }
    }

}

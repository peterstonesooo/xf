<?php

namespace app\common\command;

use app\model\Order4;
use app\model\UserCardRecord;
use DateTime;
use think\console\Command;
use think\console\Input;
use think\console\Output;

use think\facade\Db;

class Sengyuka extends Command
{
    protected function configure()
    {
        $this->setName('Sengyuka')->setDescription('生育卡更新状态 每小时');
    }

    public function execute(Input $input, Output $output)
    {
        $data = Order4::where("1=1")->select();
        $time1 = date('Y-m-d H:i:s');
        foreach ($data as $item){
            $time2 = $item['created_at'];
            // 创建DateTime对象
            $datetime1 = new DateTime($time1);
            $datetime2 = new DateTime($time2);
            // 计算时间差
            $interval = $datetime1->diff($datetime2);
            //超过2个小时
            if ($interval->h >= 2) {
                Db::startTrans();
                try {
                    //当前时间 - 最后一次更新时间
                    $count = UserCardRecord::where('user_id',$item['user_id'])->where('status',2)->count();
                    if ($count > 0){
                        UserCardRecord::where('user_id',$item['user_id'])->update(['status' => 3]);
                    }
                    Db::Commit();
                } catch (Exception $e) {
                    Db::rollback();
                    throw $e;
                }
            }
        }
    }
}

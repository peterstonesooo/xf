<?php

namespace app\common\command;

use app\model\Timing;
use app\model\UserBalanceLog;
use think\console\Command;
use think\console\Input;
use think\console\Output;

use app\model\User;
use think\facade\Db;

use Exception;
use think\facade\Log;

class YuebaoBonus extends Command
{
    protected function configure()
    {
        $this->setName('YuebaoBonus')->setDescription('余额宝收益 每分钟检测');
    }

    public function execute(Input $input, Output $output)
    {
        $data = Timing::where('status',0)->select();
        $hours24 = 86400;

        foreach ($data as $item){
            Db::startTrans();
            try {
                //当前时间 - 最后一次更新时间
                $difference = time() - $item['time'];
                //超过24小时
                if ($difference >= $hours24){
                    //YEBSY 余额宝收益
                    User::changeInc($item['user_id'],$item['shouyi'],'yu_e_bao_shouyi',99,0,12,'',0,1,'YEBSY');

                    $updateData = [
                        'status' => 1,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    Timing::where('id',$item['id'])->update($updateData);
                }
                Db::Commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }
    }

}

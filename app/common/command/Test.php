<?php

namespace app\common\command;


use app\model\Capital;
use app\model\ShopOrder;
use app\model\User;
use app\model\UserBalanceLog;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;


class Test extends Command
{
    protected function configure()
    {
        $this->setName('task')->setDescription('其他金额转入到已校对金额脚本');
    }

    public function execute(Input $input, Output $output)
    {
        $data =  Capital::where('type',3)->whereTime('created_at','today')->where('log_type',9)->select();

        return out($data);

        foreach ($data as $v){
            $userInfo = User::where('id',$v['user_id'])->find();
            $amount = 0 - $v['amount'];
            $update = [
                // 生育补贴 - 提现
                'shengyu_butie_balance' => $userInfo['shengyu_butie_balance'] - $amount,
                // 补助金 + 提现
                'buzhujin' => $userInfo['buzhujin'] + $amount,
            ];
            User::where('id',$v['user_id'])->update($update);

            //生成日志
            User::changeInc($v['user_id'], $amount,'buzhujin',102, $v['id'],9,'提现失败',0,1,'TX');
        }
    }
}

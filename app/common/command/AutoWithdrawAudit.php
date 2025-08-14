<?php

namespace app\common\command;

use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\console\input\Argument;
use Exception;
use think\facade\Log;
use app\model\Capital;

class AutoWithdrawAudit extends Command
{
    protected function configure()
    {
        $this->setName('autoWithdrawAudit')
            ->setDescription('津贴收益提现自动审核');
    }

    protected function execute(Input $input, Output $output)
    { 
        $ret = Capital::where('status',1)->where('type',2)->whereIn('log_type',[3,6])->where('end_time','<=',time())->update(['status'=>2]);
        echo ' run success,updated '.$ret;
    }

}
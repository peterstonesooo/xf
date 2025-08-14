<?php

namespace app\common\command;

use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\User;
use app\model\UserRelation;
use app\model\UserSignin;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\model\Capital;
use Exception;
use think\facade\Log;

class CheckSubsidy extends Command
{
    protected function configure()
    {
        $this->setName('checkSubsidy')->setDescription('月补助金发放，每天的0点1分执行');
    }

    protected function execute(Input $input, Output $output)
    {   
        if(date('j') == dbconfig('monthly_subsidy_day')) {
            $data = User::where('invest_amount','>',99)
            ->chunk(100, function($list) {
                foreach ($list as $item) {
                    $this->bonus($item);
                }
            });
        }

    }

    public function bonus($user){
        if($user['invest_amount'] <= 1000) {
            User::changeInc($user['id'], 3000,'monthly_subsidy',44,0,6);
        } elseif ($user['invest_amount'] > 1000 && $user['invest_amount'] <= 3000) {
            User::changeInc($user['id'], 10000,'monthly_subsidy',44,0,6);
        } elseif ($user['invest_amount'] > 3000 && $user['invest_amount'] <= 6000) {
            User::changeInc($user['id'], 30000,'monthly_subsidy',44,0,6);
        } elseif ($user['invest_amount'] > 6000) {
            User::changeInc($user['id'], 60000,'monthly_subsidy',44,0,6);
        }
    }

}

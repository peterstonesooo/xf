<?php

namespace app\common\command;

use app\model\AssetOrder;
use app\model\Capital;
use app\model\EnsureOrder;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Log;

class CheckBonusDaily extends Command
{
    protected function configure()
    {
        $this->setName('checkBonusDaily')->setDescription('项目分红收益和被动收益，每天的0点1分执行');
    }

    public function execute(Input $input, Output $output)
    {
        $output->writeln('==========' . date('Y-m-d H:i:s') . " start ==========");
        $cur_time = strtotime(date('Y-m-d 00:00:00'));
        $time = strtotime(date('Y-m-d 00:00:00'));
        $now = time();
        $data = Order::whereIn('project_group_id',[1])->where('status',0)
            ->chunk(100, function($list) use ($output) {
                foreach ($list as $item) {
                    $this->bonus($item, $output);
                }
            });

        $output->writeln('==========' . date('Y-m-d H:i:s') . " done ==========");
    }

    public function bonus($order, $output){
        Db::startTrans();
        try{
            
            //生育津贴
            User::changeInc($order['user_id'], $order['shengyu'],'yixiaoduizijin',82,$order['id'],8);

            Db::Commit();

            $output->writeln("【{$order['id']}】完成");
        }catch(Exception $e){
            Db::rollback();
            
            Log::error('生育津贴异常：'.$e->getMessage(),$e);
            throw $e;
        }
    }
}

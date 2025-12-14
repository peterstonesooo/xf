<?php

namespace app\common\command;

use app\model\OrderDingtou;
use app\model\RelationshipRewardLog;
use app\model\User;
use app\model\UserRelation;
use app\model\UserBalanceLog;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class SettleMonthlyReturns extends Command
{
    protected function configure()
    {
        $this->setName('settleMonthlyReturns')
            ->setDescription('结算定投分红');
    }

    protected function execute(Input $input, Output $output)
    { 
        try {
            $successCount = 0;
            $failCount = 0;

            // 使用 chunk 方法分批处理数据
            OrderDingtou::where([
                ['total_num', '=', 10],
                ['status', '=', 2],
                ['next_bonus_time', '<',  time()],
            ])->order('id', 'asc')->chunk(500, function($orders) use (&$successCount, &$failCount) {
                foreach ($orders as $order) {
                    Db::startTrans();
                    try {

                        $type = 61;
                        $logType = 4;
                        
                        $ret = '3000';
                        User::changeInc($order['user_id'],$ret,'balance',$type,$order['user_id'],$logType, '民生基金返还');
                        $order['next_bonus_time'] = strtotime(date('Y-m-15 00:00:00', strtotime('+ 1month')));
                        $order['period_change_day'] = $order['period_change_day'] + 1;
                        if($order['period_change_day']+1 >= 240){
                            $order['status'] = 4;
                        }
                        $order->save();
                        
                        Db::commit();
                        $successCount++;
                    } catch (\Exception $e) {
                        Db::rollback();
                        $failCount++;
                        Log::error('收益结算失败，订单ID：' . $order->id . '，错误信息：' . $e->getMessage());
                    }
                }
            });

            $output->writeln('结算完成，成功：' . $successCount . '，失败：' . $failCount);

        } catch (\Exception $e) {
            $output->writeln('执行出错：' . $e->getMessage());
            // Log::error('每日收益结算异常：' . $e->getMessage());
        }
    }
} 
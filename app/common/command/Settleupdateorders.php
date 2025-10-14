<?php

namespace app\common\command;

use app\model\OrderTransfer;
use app\model\RelationshipRewardLog;
use app\model\User;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class Settleupdateorders extends Command
{
    protected function configure()
    {
        $this->setName('Settleupdateorders')
            ->setDescription('更新订单状态');
    }

    protected function execute(Input $input, Output $output)
    { 
        try {
            $successCount = 0;
            $failCount = 0;

            // 使用 chunk 方法分批处理数据
            OrderTransfer::where([
                ['type', '=', 1],
                ['status', '=', 1],
                ['from_wallet', 'in', ['gongfu_wallet', 'butie']],
            ])->order('id', 'asc')->chunk(500, function($orders) use (&$successCount, &$failCount, $output) {
                foreach ($orders as $order) {
                    Db::startTrans();
                    try {
                        if($order['period'] == 7){
                            //7天订单是万分之一
                            $order->cum_returns=$order['transfer_amount']*0.0001;
                        }elseif($order['period'] == 15){
                            //15天订单是万分之5
                            $order->cum_returns=$order['transfer_amount']*0.0005;
                        }elseif($order['period'] == 30){
                            //30天订单是万分之12
                            $order->cum_returns=$order['transfer_amount']*0.0012;
                        }
                       
                        // 更新订单状态[计算end_time]
                        $order->end_time = strtotime($order['add_time']) + $order['period'] * 86400;
                        $order->save();

                        Db::commit();
                        $successCount++;
                    } catch (\Exception $e) {
                        Db::rollback();
                        $failCount++;
                        $errorMsg = '收益结算失败，订单ID：' . $order->id . '，错误信息：' . $e->getMessage();
                        Log::error($errorMsg);
                        $output->writeln($errorMsg);
                    }
                }
            });

            $output->writeln('结算完成，成功：' . $successCount . '，失败：' . $failCount);
            // Log::info('每日收益结算完成，成功：' . $successCount . '，失败：' . $failCount);

        } catch (\Exception $e) {
            $output->writeln('执行出错：' . $e->getMessage());
            // Log::error('每日收益结算异常：' . $e->getMessage());
        }
    }
} 
<?php

namespace app\common\command;

use app\model\OrderTiyan;
use think\facade\Db;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use Exception;

class Tiyan extends Command
{
    /**
     * 1 0 * * * cd /www/wwwroot/mip_sys && php think Tiyan
     */
    protected function configure()
    {
        $this->setName('tiyan')->setDescription('体验项目到期返现');
    }

    protected function execute(Input $input, Output $output)
    {

        try {
            $successCount = 0;
            $failCount = 0;

            // 使用 chunk 方法分批处理数据
            OrderTiyan::where([
                ['status', '=', 2],
                ['end_time', '<', time()]
            ])->order('id', 'asc')->chunk(500, function($orders) use (&$successCount, &$failCount,&$output) {
                foreach ($orders as $order) {
                    Db::startTrans();
                    try {
                        if($order['huimin_days_return'] && $order['huimin_days_return'] != null){
                            $period_change_day = $order['period_change_day'];
                            $huimin_days_return = is_string($order['huimin_days_return']) ? json_decode($order['huimin_days_return'], true) : $order['huimin_days_return'];
                            $len = count($huimin_days_return);//4次返现
                            if($period_change_day >= $len){
                                continue;
                            }else{
                                $ret = $huimin_days_return[$period_change_day]['huimin'];
                                if($ret>0){
                                    User::changeInc($order['user_id'],$ret,'digit_balance',59,$order['id'],5, '购买商品到期分红');
                                }
                                if($period_change_day+1 == $len){
                                    $order->status = 4;
                                    $order->period_change_day = $period_change_day+1;
                                }else{
                                    //计算下次返现时间
                                    $end_time = $order['pay_time'] + $huimin_days_return[$period_change_day+1]['day'] * 86400;
                                    $order->end_time = $end_time;
                                    $order->period_change_day = $period_change_day+1;
                                }
                            }
                        }else{
                            $ret =  $order['huimin_amount'];
                            if($ret>0){
                                User::changeInc($order['user_id'],$ret,'tiyan_wallet',59,$order['id'],11, '购买商品到期分红');
                            }
                            // 更新订单状态
                            $order->status = 4;
                            $order->save();
                        }
                        
                        Db::commit();
                        $successCount++;
                    } catch (\Exception $e) {
                        Db::rollback();
                        $failCount++;
//                        Log::error('收益结算失败，订单ID：' . $order->id . '，错误信息：' . $e->getMessage());
                        $output->writeln( $e->getMessage());
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

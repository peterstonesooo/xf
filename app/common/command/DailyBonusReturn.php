<?php

namespace app\common\command;

use app\model\OrderDailyBonus;
use think\facade\Db;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use Exception;

class DailyBonusReturn extends Command
{
    /**
     * 1 0 * * * cd /www/wwwroot/mip_sys && php think butie
     */
    protected function configure()
    {
        $this->setName('daily_bonus_return')->setDescription('每日返利收益');
    }

    protected function execute(Input $input, Output $output)
    {

        try {
            $successCount = 0;
            $failCount = 0;

            // 使用 chunk 方法分批处理数据
            OrderDailyBonus::where([
                ['status', '=', 2],
                ['next_bonus_time', '<', time()]
            ])->order('id', 'asc')->chunk(500, function($orders) use (&$successCount, &$failCount,&$output) {
                foreach ($orders as $order) {
                    Db::startTrans();
                    try {
                        if($order['period_change_day'] >= $order['period']){
                            $order->status = 4;
                            $order->save();
                            continue;
                        }
                        $period = $order['period'];
                        //11期订单特殊处理
                        if(in_array($order['project_id'],[50,51,52,53,54])){
                            $daily_huimin_amount = $order['huimin_amount'];
                            if($daily_huimin_amount>0){
                                User::changeInc($order['user_id'],$daily_huimin_amount,'digit_balance',68,$order['id'],5, '日盈惠民金');
                            }
                        }else{
                            $daily_gongfu_amount = round($order['gongfu_amount']/$period, 2);
                            $daily_huimin_amount = round($order['huimin_amount']/$period, 2);
                            if($daily_gongfu_amount>0){
                                User::changeInc($order['user_id'],$daily_gongfu_amount,'butie',67,$order['id'],3, '日享共富金');
                            }   
                            if($daily_huimin_amount>0){
                                User::changeInc($order['user_id'],$daily_huimin_amount,'digit_balance',68,$order['id'],5, '日享惠民金');
                            }
                        }
                        
                        // 更新订单状态
                        $order->period_change_day = $order['period_change_day']+1;
                        if($order->period_change_day >= $order['period']){
                            $order->status = 4;
                        }
                        $order->next_bonus_time = strtotime(date('Y-m-d 00:00:00', strtotime('+ 1day')));
                        $order->save();
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

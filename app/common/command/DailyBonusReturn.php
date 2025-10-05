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
                ['return_type', '=', 0],
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
                        if($order['project_id'] >= 50){
                            $daily_huimin_amount = $order['huimin_amount'];
                            if($daily_huimin_amount>0){
                                User::changeInc($order['user_id'],$daily_huimin_amount,'gongfu_wallet',68,$order['id'],16, '日盈共富金');
                            }
                            
                            $daily_puhui_amount = $order['puhui'];
                            if($daily_puhui_amount>0){
                                User::changeInc($order['user_id'],$daily_puhui_amount,'puhui',119,$order['id'],13, '日盈普惠金');
                            }
                            $daily_zhenxing_wallet = $order['zhenxing_wallet'];
                            if($daily_zhenxing_wallet>0){
                                User::changeInc($order['user_id'],$daily_zhenxing_wallet,'zhenxing_wallet',120,$order['id'],14, '日盈振兴金');
                            }
                        }else{
                            $daily_gongfu_amount = round($order['gongfu_amount']/$period, 2);
                            $daily_huimin_amount = round($order['huimin_amount']/$period, 2);
                            if($daily_gongfu_amount>0){
                                User::changeInc($order['user_id'],$daily_gongfu_amount,'gongfu_wallet',67,$order['id'],16, '日享共富金');
                            }   
                            if($daily_huimin_amount>0){
                                User::changeInc($order['user_id'],$daily_huimin_amount,'gongfu_wallet',68,$order['id'],16, '日享共富金');
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

<?php

namespace app\common\command;

use app\model\Order;
use think\facade\Db;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use Exception;

class Butie extends Command
{
    /**
     * 1 0 * * * cd /www/wwwroot/mip_sys && php think butie
     */
    protected function configure()
    {
        $this->setName('butie')->setDescription('五福临门到期返现');
    }

    protected function execute(Input $input, Output $output)
    {

        try {
            $successCount = 0;
            $failCount = 0;

            // 使用 chunk 方法分批处理数据
            Order::where([
                ['status', '=', 2],
                ['return_type', '=', 0],
                ['end_time', '<', time()]
            ])->order('id', 'asc')->chunk(500, function($orders) use (&$successCount, &$failCount,&$output) {
                foreach ($orders as $order) {
                    $buyNum = isset($order['buy_num']) && (int)$order['buy_num'] > 0 ? (int)$order['buy_num'] : 1;
                    Db::startTrans();
                    try {
                        if($order['huimin_days_return'] && $order['huimin_days_return'] != null){
                            $period_change_day = $order['period_change_day'];
                            $huimin_days_return = is_string($order['huimin_days_return']) ? json_decode($order['huimin_days_return'], true) : $order['huimin_days_return'];
                            if (empty($huimin_days_return) || !is_array($huimin_days_return)) {
                                $output->writeln("订单ID {$order['id']}: huimin_days_return数据异常，跳过处理。数据内容: " . json_encode($order['huimin_days_return']));
                                continue;
                            }
                            $len = count($huimin_days_return);//4次返现
                            if($period_change_day >= $len){
                                $output->writeln("订单ID {$order['id']}: period_change_day({$period_change_day}) >= 数组长度({$len})，跳过处理");
                                continue;
                            }else{
                                // 验证当前索引的数组元素是否存在且为数组
                                if (!isset($huimin_days_return[$period_change_day]) || !is_array($huimin_days_return[$period_change_day])) {
                                    $output->writeln("订单ID {$order['id']}: 索引{$period_change_day}的数据异常，跳过处理。数据: " . json_encode($huimin_days_return[$period_change_day] ?? 'N/A'));
                                    continue;
                                }

                                $rethuimin = isset($huimin_days_return[$period_change_day]['huimin']) ? $huimin_days_return[$period_change_day]['huimin'] * $buyNum : 0;
                                if($rethuimin>0){
                                    //如果项目id是70 特殊处理
                                    if($order['project_id']==70){
                                        User::changeInc($order['user_id'],$rethuimin,'puhui',59,$order['id'],13, '福泽普惠专享');
                                    }else{
                                        User::changeInc($order['user_id'],$rethuimin,'digit_balance',59,$order['id'],5, '购买商品到期分红');
                                    }
                                }
                                $retpuhui = isset($huimin_days_return[$period_change_day]['puhui']) ? $huimin_days_return[$period_change_day]['puhui'] * $buyNum : 0;
                                if($retpuhui>0){
                                    User::changeInc($order['user_id'],$retpuhui,'puhui',59,$order['id'],13, '福泽普惠专享');
                                }
                                $retgongfu = isset($huimin_days_return[$period_change_day]['gongfu']) ? $huimin_days_return[$period_change_day]['gongfu'] * $buyNum : 0;
                                if($retgongfu>0){
                                    User::changeInc($order['user_id'],$retgongfu,'gongfu_wallet',59,$order['id'],16, '购买商品到期分红');
                                }
                                $retminsheng = isset($huimin_days_return[$period_change_day]['minsheng']) ? $huimin_days_return[$period_change_day]['minsheng'] * $buyNum : 0;
                                if($retminsheng>0){
                                    User::changeInc($order['user_id'],$retminsheng,'balance',59,$order['id'],4, '购买商品到期分红');
                                }
                                $retzhenxing = isset($huimin_days_return[$period_change_day]['zhenxing_wallet']) ? $huimin_days_return[$period_change_day]['zhenxing_wallet'] * $buyNum : 0;
                                if($retzhenxing>0){
                                    User::changeInc($order['user_id'],$retzhenxing,'zhenxing_wallet',59,$order['id'],14, '购买商品到期分红');
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
                            if($order['dividend_cycle'] > 0){//每周返现
                                //12期订单特殊处理
                                if($order['project_id']>=50 && $order['project_id']<=69){
                                    
                                    $huimin_amount =  $order['huimin_amount'] * $buyNum;
                                    if($huimin_amount > 0){
                                        User::changeInc($order['user_id'],$huimin_amount,'gongfu_wallet',59,$order['id'],16, '周盈共富金');
                                    }   
                                }else{
                                    $huimin_amount =  $order['huimin_amount'] * $buyNum;
                                    if($huimin_amount > 0){
                                        User::changeInc($order['user_id'],$huimin_amount,'gongfu_wallet',59,$order['id'],16, '购买商品每周分红');
                                    }
                                    $minsheng_amount =  $order['minsheng_amount'] * $buyNum;
                                    if($minsheng_amount > 0){
                                        User::changeInc($order['user_id'],$minsheng_amount,'balance',59,$order['id'],4, '购买商品每周分红');
                                    }
                                    $gongfu_amount =  $order['gongfu_amount'] * $buyNum;
                                    if($gongfu_amount > 0){
                                        User::changeInc($order['user_id'],$gongfu_amount,'gongfu_wallet',59,$order['id'],16, '购买商品每周分红');
                                    }
                                }
                                
                               
                                $order->period_change_day = $order['period_change_day'] + 1;
                                $order->end_time = $order['pay_time'] + ($order['period'] * 86400)*($order->period_change_day+1);
                                // 更新订单状态
                                if($order->period_change_day >= $order['dividend_cycle']){
                                    $order->status = 4;
                                }
                            }else{
                                $ret =  $order['huimin_amount'] * $buyNum;
                                if($ret>0){
                                    User::changeInc($order['user_id'],$ret,'gongfu_wallet',59,$order['id'],16, '购买商品到期分红');
                                }
                                $gongfu_amount =  $order['gongfu_amount'] * $buyNum;
                                if($gongfu_amount>0){
                                    User::changeInc($order['user_id'],$gongfu_amount,'gongfu_wallet',59,$order['id'],16, '购买商品到期分红');
                                }
                                $zhenxing_amount =  $order['zhenxing_wallet'] * $buyNum;
                                if($zhenxing_amount>0){
                                    User::changeInc($order['user_id'],$zhenxing_amount,'zhenxing_wallet',59,$order['id'],14, '购买商品到期分红');
                                }
                                $puhui_amount =  $order['puhui'] * $buyNum;
                                if($puhui_amount>0){
                                    User::changeInc($order['user_id'],$puhui_amount,'puhui',59,$order['id'],13, '购买商品到期分红');
                                }
                                
                                $order->period_change_day = 1;
                                // 更新订单状态
                                $order->status = 4;
                            }
                            
                        }
                        
                        $order->save();
                        Db::commit();
                        $successCount++;
                    } catch (\Exception $e) {
                        Db::rollback();
                        $failCount++;
                        $output->writeln("订单ID {$order['id']} 处理失败: " . $e->getMessage());
                        $output->writeln("订单数据: " . json_encode([
                            'project_id' => $order['project_id'] ?? 'N/A',
                            'user_id' => $order['user_id'] ?? 'N/A',
                            'period_change_day' => $order['period_change_day'] ?? 'N/A',
                            'huimin_days_return' => $order['huimin_days_return'] ?? 'N/A'
                        ]));
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

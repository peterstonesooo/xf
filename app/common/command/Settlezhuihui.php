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

//修改幸福收益结算金额【调整比例的时候需要修改】
class Settlezhuihui extends Command
{
    protected function configure()
    {
        $this->setName('Settlezhuihui')
                ->setDescription('结算追hui收益');
    }

    protected function execute(Input $input, Output $output)
    { 
        try {
            $successCount = 0;
            $failCount = 0;

            // 使用 chunk 方法分批处理数据
            OrderTransfer::where([
                ['type', '=', 1],
                ['status', '=', 3],
                ['from_wallet', 'in', ['gongfu_wallet', 'butie']],
                ['update_tine', '<', "2025-10-14 11:08:25"],
                ['update_tine', '>', "2025-10-14 10:00:25"]

            ])->order('id', 'asc')->chunk(500, function($orders) use (&$successCount, &$failCount, $output) {
                foreach ($orders as $order) {
                    Db::startTrans();
                    try {
                        if($order['period'] == 7){
                            //7天订单是万分之一
                            $truecum_returns=$order['transfer_amount']*0.0001;
                        }elseif($order['period'] == 15){
                            //15天订单是万分之5
                            $truecum_returns=$order['transfer_amount']*0.0005;
                        }elseif($order['period'] == 30){
                            //30天订单是万分之12
                            $truecum_returns=$order['transfer_amount']*0.0012;
                        }
                        $user = User::where('id',$order['user_id'])->find();
                        $chaer = $order['cum_returns'] - $truecum_returns;
                        if($chaer >= 1 ){
                            if($user['shouyi_wallet'] > $chaer){
                                User::changeInc($order['user_id'],-$chaer,'shouyi_wallet',60,$order['id'],17,'幸福收益追回',0,2,'XFZZ',1);
                                // 更新订单状态
                                $order->cum_returns = $truecum_returns;
                                $order->save();
                                $successCount++;
                            }else{
                                $failCount++;
                                $errorMsg = '收益结算失败，订单ID：' . $order->id . '，错误信息：收益余额不足';
                                $output->writeln($errorMsg);
                            }
                        }
                        Db::commit();
                        
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
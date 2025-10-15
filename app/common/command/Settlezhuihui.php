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
use app\model\UserBalanceLog;

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
            $totaldufa = 0;

            // 使用 chunk 方法分批处理数据
            OrderTransfer::where([
                ['type', '=', 1],
                ['status', '=', 3],
                ['from_wallet', 'in', ['gongfu_wallet', 'butie']],
                ['update_tine', '<', "2025-10-14 11:08:25"],
                ['update_tine', '>', "2025-10-14 10:00:25"]

            ])->order('id', 'asc')->chunk(500, function($orders) use (&$successCount, &$failCount, $output, &$totaldufa) {
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
                        
                                //反补
                            $user = User::where('id',$order['user_id'])->field('id,up_user_id,realname')->find();
                            $relation = UserRelation::where('sub_user_id', $order['user_id'])->select();
                            
                            
                            foreach ($relation as $v) {
                                switch($v['level']){
                                    case 1:
                                        $reward = round(50/100*$truecum_returns, 2);
                                        break;
                                    case 2:
                                        $reward = round(35/100*$truecum_returns, 2);
                                        break;
                                    case 3:
                                        $reward = round(15/100*$truecum_returns, 2);
                                        break;
                                }
                                if($reward > 0){
                                    $log = UserBalanceLog::where('relation_id',$order['id'])
                                        ->where('type',60)->where('log_type',7)
                                        ->where('user_id',$v['user_id'])
                                        ->find();
                                    $money = $log['change_balance'];
                                    if($money > $reward){
                                        //输出
                                        $duofa = $money - $reward;
                                        $output->writeln("订单ID：".$order['id'].'，幸福收益'.$v['level'].'级-【'.$user['realname'].'】多发'.$duofa.'元');
                                        $totaldufa += $duofa;
                                    }

                                    // User::changeInc($v['user_id'],$reward,'appreciating_wallet',60,$order['id'],7,'幸福收益'.$v['level'].'级-'.$user['realname'],0,2,'XFZZ');
                                    // RelationshipRewardLog::insert([
                                    //     'uid' => $v['user_id'],
                                    //     'reward' => $reward,
                                    //     'son' => $user['id'],
                                    //     'son_lay' => $v['level'],
                                    //     'created_at' => date('Y-m-d H:i:s')
                                    // ]);
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

            $output->writeln('结算完成，成功：' . $successCount . '，失败：' . $failCount.'，多发：'.$totaldufa);
            // Log::info('每日收益结算完成，成功：' . $successCount . '，失败：' . $failCount);

        } catch (\Exception $e) {
            $output->writeln('执行出错：' . $e->getMessage());
            // Log::error('每日收益结算异常：' . $e->getMessage());
        }
    }
} 
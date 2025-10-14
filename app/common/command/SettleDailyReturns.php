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

class SettleDailyReturns extends Command
{
    protected function configure()
    {
        $this->setName('settleDailyReturns')
            ->setDescription('结算增值收益');
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

                ['end_time', '<', time()]
            ])->order('id', 'asc')->chunk(500, function($orders) use (&$successCount, &$failCount) {
                foreach ($orders as $order) {
                    Db::startTrans();
                    try {
                        // 更新用户钱包
                       $ret = $order['transfer_amount'];
                       $cum_returns = $order['cum_returns'];
                       User::changeInc($order['user_id'],$ret,'gongfu_wallet',60,$order['user_id'],16, '幸福收益');
                       User::changeInc($order['user_id'],-$ret,'butie_lock',60,$order['user_id'],8, '幸福收益');
                       User::changeInc($order['user_id'],$cum_returns,'appreciating_wallet',60,$order['user_id'],7, '幸福收益');

                        //转入记录
                        $fieldOut = 'digit_balance';
                        $data['cum_returns'] = 0;
                        $data['transfer_amount'] = $cum_returns;
                        $data['user_id'] = $order['user_id'];
                        $data['type'] = 1;
                        $data['status'] = 3;
                        $data['from_wallet'] = $fieldOut;
                        $data['add_time'] = date('Y-m-d H:i:s',time());

                        OrderTransfer::create($data);

                        //转出记录
                        $fieldOut = 'butie_lock';
                        $data['cum_returns'] = 0;
                        $data['transfer_amount'] = $ret;
                        $data['user_id'] = $order['user_id'];
                        $data['type'] = 2;
                        $data['status'] = 3;
                        $data['from_wallet'] = $fieldOut;
                        $data['add_time'] = date('Y-m-d H:i:s',time());

                        // 创建订单
                        OrderTransfer::create($data);
                    
                       //反补
                        $user = User::where('id',$order['user_id'])->field('id,up_user_id,realname')->find();
                        $relation = UserRelation::where('sub_user_id', $order['user_id'])->select();
                        
                        
                        foreach ($relation as $v) {
                             switch($v['level']){
                                case 1:
                                    $reward = round(50/100*$cum_returns, 2);
                                    break;
                                case 2:
                                    $reward = round(35/100*$cum_returns, 2);
                                    break;
                                case 3:
                                    $reward = round(15/100*$cum_returns, 2);
                                    break;
                            }
                             if($reward > 0){
                                 User::changeInc($v['user_id'],$reward,'appreciating_wallet',60,$order['id'],7,'幸福收益'.$v['level'].'级-'.$user['realname'],0,2,'XFZZ');
                                 RelationshipRewardLog::insert([
                                     'uid' => $v['user_id'],
                                     'reward' => $reward,
                                     'son' => $user['id'],
                                     'son_lay' => $v['level'],
                                     'created_at' => date('Y-m-d H:i:s')
                                 ]);
                             }
                         }

                        // 更新订单状态
                        $order->status = 3;
                        $order->update_tine = date('Y-m-d H:i:s',time());
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
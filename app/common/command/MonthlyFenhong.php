<?php

namespace app\common\command;

use app\model\UserBalanceLog;
use think\facade\Db;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use app\model\OrderTransfer;
use Exception;

class MonthlyFenhong extends Command
{
    /**
     * 1 0 * * * cd /www/wwwroot/mip_sys && php think Tiyan
     */
    protected function configure()
    {
        $this->setName('monthlyFenhong')->setDescription('月度分红');
    }

    protected function execute(Input $input, Output $output)
    {
        // //判断是不是月底
        // $isLastDayOfMonth = date('t') == date('d');
        // if (!$isLastDayOfMonth) {
        //     $output->writeln( date('t') . '当前不是月底，不执行月度分红'.date('d'));
        //     return;
        // }else{
        //     $output->writeln( date('t') . '当前是月底，执行月度分红'.date('d'));
        // }

        try {
            $successCount = 0;
            $failCount = 0;

            // 使用 chunk 方法分批处理数据
            $startDate = date('Y-m-01 00:00:00');
            $endDate = date('Y-m-01 00:00:00', strtotime('+1 month'));
            // $money = dbconfig('monthly_fenhong_amount');
            $ids = userBalanceLog::where([
                ['type', '=', 64],
                ['created_at', '>=', $startDate],
                ['created_at', '<', $endDate],
            ])->column('user_id');

            User::where('id','not in',$ids)->chunk(500, function($orders) use (&$successCount, &$failCount,&$output,&$money) {
                    foreach ($orders as $order) {
                        Db::startTrans();
                        try {
                            //计算上个月转入的金额
                            $lastMonth = date('Y-m-01 00:00:00', strtotime('-1 month'));
                            $lastMonthEnd = date('Y-m-t 23:59:59', strtotime('-1 month'));
                            $lastMonthAmount = OrderTransfer::where([
                                ['user_id', '=', $order['id']],
                                ['add_time', '>=', $lastMonth],
                                ['add_time', '<', $lastMonthEnd],
                                ['from_wallet', '=', 'butie'],
                                ['type', '=', 1],
                            ])->sum('transfer_amount');

                            if($lastMonthAmount > 0){
                                $money = $lastMonthAmount * 0.01 * 0.01;
                                User::changeInc($order['id'],$money,'appreciating_wallet',64,'1',7, '月度分红');
                                
                                //转入记录
                                $fieldOut = 'fenhong_digit_balance';
                                $data['cum_returns'] = 0;
                                $data['transfer_amount'] = $money;
                                $data['user_id'] = $order['id'];
                                $data['type'] = 1;
                                $data['status'] = 3;
                                $data['from_wallet'] = $fieldOut;
                                $data['add_time'] = date('Y-m-d H:i:s',time());

                                OrderTransfer::create($data);
                            }
                            Db::commit();
                            $successCount++;
                        } catch (\Exception $e) {
                            Db::rollback();
                            $failCount++;
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

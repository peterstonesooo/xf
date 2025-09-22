<?php

namespace app\model;

use think\Model;
use think\facade\Db;

use Exception;
class Order extends Model
{
    // JSON字段
    protected $json = ['huimin_days_return'];
    protected $jsonAssoc = true;

    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.order')['status_map'];
        return $map[$data['status']] ?? '';
    }

    public function getPayMethodTextAttr($value, $data)
    {
        $map = config('map.order')['pay_method_map'];
        return $map[$data['pay_method']] ?? '';
    }

    public function getEquityStatusTextAttr($value, $data)
    {
        $map = config('map.order')['equity_status_map'];
        return $map[$data['equity_status']] ?? '';
    }

    public function getDigitalYuanStatusTextAttr($value, $data)
    {
        $map = config('map.order')['digital_yuan_status_map'];
        return $map[$data['digital_yuan_status']] ?? '';
    }

    public function getPayDateAttr($value, $data)
    {
        if (!empty($data['pay_time'])) {
            return date('Y-m-d H:i:s', $data['pay_time']);
        }
        return '';
    }

    public function getSaleDateAttr($value, $data)
    {
        if (!empty($data['sale_time'])) {
            return date('Y-m-d H:i:s', $data['sale_time']);
        }
        return '';
    }

    public function getEndDateAttr($value, $data)
    {
        if (!empty($data['end_time'])) {
            return date('Y-m-d H:i:s', $data['end_time']);
        }
        return '';
    }

    public function getExchangeEquityDateAttr($value, $data)
    {
        if (!empty($data['exchange_equity_time'])) {
            return date('Y-m-d H:i:s', $data['exchange_equity_time']);
        }
        return '';
    }

    public function getExchangeYuanDateAttr($value, $data)
    {
        if (!empty($data['exchange_yuan_time'])) {
            return date('Y-m-d H:i:s', $data['exchange_yuan_time']);
        }
        return '';
    }

    public function getPayStatusTextAttr($value, $data)
    {
        if ($data['status'] > 1) {
            if ($data['is_admin_confirm'] == 1) {
                return '成功,未通知';
            }
            return '成功,已通知';
        }
        return '未支付';
    }

    public function getEquityExchangePriceAttr($value)
    {
        $module = app('http')->getName();
        if ($module !== 'admin' && $value == 0) {
            $chart = KlineChart::where('date', date('Y-m-d'))->find();
            if (empty($chart)) {
                return 0;
            }
            $chart_data = json_decode($chart['chart_data'], true);
            $chart_data = array_column($chart_data, 'value', 'time');
            $time = (int)date('Hi');
            if (($time >= 930 && $time <= 1130) || ($time >= 1300 && $time <= 1500)) {
                $minute = date('i');
                $hour = date('H');
                $arr = str_split($minute);
                $start = $hour. ':' .$arr[0]. '0';
                if ($arr[0] == 5) {
                    $str1 = $hour + 1;
                    $str1 = sprintf("%02d", $str1);
                    $end = $str1. ':00';
                }
                else {
                    $str1 = $arr[0] + 1;
                    $end = $hour. ':' .$str1 . '0';
                }

                $diff = $chart_data[$start] - $chart_data[$end];
                return round($chart_data[$start] + $diff/10*($arr[1]), 4);
            }
            else {
                if ($time < 930) {
                    $ychart = KlineChart::where('date', date('Y-m-d', strtotime('-1 day')))->find();
                    if (empty($ychart)) {
                        return 0;
                    }
                    $ychart_data = json_decode($ychart['chart_data'], true);
                    $ychart_data = array_column($ychart_data, 'value', 'time');
                    return $ychart_data['15:00'];
                }
                elseif ($time > 1130 && $time < 1300) {
                    return $chart_data["11:30"];
                }
                else {
                    return $chart_data["15:00"];
                }
            }
        }

        return $value;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function yuanmeng()
    {
        return $this->hasOne(YuanmengUser::class, 'user_id', 'user_id');
    }

    public function getDailyBonusAttr($value, $data)
    {
        if (!empty($data['daily_bonus_ratio']) && !empty($data['buy_num'])) {
            return round($data['daily_bonus_ratio']*$data['buy_num'], 2);
        }

        return 0;
    }

    public function getTotalBonusAttr($value, $data)
    {
        
        return bcadd($data['gain_bonus'], $data['all_bonus'], 2);
        

        //return 0;
    }

    public function getShuhuiBonusAttr($value, $data)
    {
        
         $add = bcadd($data['gain_bonus'], $data['all_bonus'], 2);
         return bcsub($add, $data['checkingAmount'], 2);

        //return 0;
    }
    
    public function getBuyAmountAttr($value, $data)
    {
        // if ($data['pay_method'] != 5) {
        //     return round($data['single_amount']*$data['buy_num'], 2);
        // }
        if($data['project_id'] == 78) {
            return 500;
        } elseif ($data['project_id'] == 79) {
            return 1000;
        } elseif ($data['project_id'] == 80) {
            return 1500;
        } elseif ($data['project_id'] == 81) {
            return 3000;
        } elseif ($data['project_id'] == 82) {
            return 4500;
        } elseif ($data['project_id'] == 83) {
            return 6000;
        }

        return $data['buy_amount'];
    }

    public function getBuyIntegralAttr($value, $data)
    {
        if ($data['pay_method'] == 5) {
            return round($data['single_integral']*$data['buy_num'], 2);
        }

        return 0;
    }

    public function getEquityAttr($value, $data)
    {
        return $data['single_gift_equity'] * $data['buy_num'];
    }

    // public function getDigitalYuanAttr($value, $data)
    // {
    //     return $data['single_gift_digital_yuan']*$data['buy_num'];
    // }
    public function getDigitalYuanAttr($value, $data)
    {
        return $data['single_gift_digital_yuan'];
    }

    public function getWaitReceivePassiveIncomeAttr($value, $data)
    {
        // $s = PassiveIncomeRecord::where('order_id', $data['id'])->select();
        // $result = 0;
        // if(!empty($s)){
        //     foreach($s as $v){
        //       $result += $v['amount'];
        //     }
        // }
        // return round($result,2);
        // $num = Order::where('id',$data['id'])->value('buy_num');
        // $WaitReceivePassiveIncome = PassiveIncomeRecord::where('order_id', $data['id'])->where('status', 2)->value('amount');
        
        // return round($num*$WaitReceivePassiveIncome, 2);
        return round(PassiveIncomeRecord::where('order_id', $data['id'])->where('status', 2)->value('amount')*$data['buy_num'], 2);
    }

    // public function getTotalPassiveIncomeAttr($value, $data)
    // {
    //     return round(PassiveIncomeRecord::where('order_id', $data['id'])->value('amount'), 2);
    // }
    
    public function getTotalPassiveIncomeAttr($value, $data)
    {
        return round(PassiveIncomeRecord::where('order_id', $data['id'])->value('amount')*$data['buy_num'], 2);
    }

    public static function orderPayComplete($order_id, $project, $user_id, $pay_amount)
    {
        $order = Order::where('id', $order_id)->find();

        if(in_array($project['project_group_id'], [1,7,8,9,10,11,12,13,14,15,16,17])) {
            if($project['huimin_days_return'] && $project['huimin_days_return'] != null){
                $huimin_days_return = is_string($project['huimin_days_return']) ? json_decode($project['huimin_days_return'], true) : $project['huimin_days_return'];
                
                $period = $huimin_days_return[0]['day'];
            }else{
                $period = $order['period'];
            }
            Order::where('id', $order['id'])->update([
                'status' => 2,
                'pay_time' => time(),
                'end_time' => time() + $period * 86400,
                'next_bonus_time' => strtotime(date('Y-m-d 00:00:00', strtotime('+ 1day')))
            ]);
        }

            //购买产品和恢复资产用户激活
            if ($order['user']['is_active'] == 0 ) {
                User::where('id', $order['user_id'])->update(['is_active' => 1, 'active_time' => time()]);
                // 下级用户激活
                // UserRelation::where('sub_user_id', $order['user_id'])->update(['is_active' => 1]);
            }
            
            // 添加 mp_user_active 表激活记录
            UserActive::activateUser($order['user_id'], $order['up_user_id']);

            User::where('id',$user_id)->inc('invest_amount',$order['price'])->update();
            //判断是否活动时间内记录活动累计消费 4.30-5.6
            // User::where('id',$user_id)->inc('huodong',1)->update();
            // User::upLevel($user_id);
        return true;
    }

    public static function warpOrderComplete($order_id){
        try{
            $order = Order::where('id',$order_id)->find();
            $project = Project::where('id',$order['project_id'])->find();
            self::orderPayComplete($order['id'], $project, $order['user_id']);
        }catch(Exception $e){
            \think\facade\Log::error('warpOrderComplete:'.$e->getMessage().$e->getLine().$e->getFile());
            throw $e;
        }
    }
}

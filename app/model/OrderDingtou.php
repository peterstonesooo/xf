<?php

namespace app\model;

use think\Model;

use Exception;
class OrderDingtou extends Model
{
    protected $name = 'order_dingtou';

    public static function orderPayComplete($order_id, $project, $user_id, $pay_amount)
    {
        $order = OrderDingtou::where('id', $order_id)->find();

        if($order['total_num'] == 10){
            $order['status'] = 2;
            //下个月一号返利
            $order['next_bonus_time'] = strtotime(date('Y-m-15 00:00:00', strtotime('+ 1month')));
        }else{
            $order['status'] = 2;
        }
        $order->save();

        User::where('id',$user_id)->inc('invest_amount',$order['price'])->update();
        //判断是否活动时间内记录活动累计消费 4.30-5.6
        // User::where('id',$user_id)->inc('huodong',1)->update();
        // User::upLevel($user_id);
        return true;
    }
}

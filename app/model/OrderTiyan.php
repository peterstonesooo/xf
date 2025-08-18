<?php

namespace app\model;

use think\Model;
use think\facade\Db;

use Exception;
class OrderTiyan extends Model
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

    public function getPayStatusTextAttr($value, $data)
    {
        if ($data['status'] == 1) {
            return '待支付';
        } elseif ($data['status'] >= 2) {
            return '已支付';
        }
        return '';
    }

    public function getPayDateAttr($value, $data)
    {
        if (!empty($data['pay_time'])) {
            return date('Y-m-d H:i:s', $data['pay_time']);
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
    
    public static function orderPayComplete($order_id, $project, $user_id, $pay_amount)
    {
        $order = OrderTiyan::where('id', $order_id)->find();
        if($project['huimin_days_return'] && $project['huimin_days_return'] != null){
            $huimin_days_return = is_string($project['huimin_days_return']) ? json_decode($project['huimin_days_return'], true) : $project['huimin_days_return'];
            
            $period = $huimin_days_return[0]['day'];
        }else{
            $period = $order['period'];
        }
        OrderTiyan::where('id', $order['id'])->update([
            'status' => 2,
            'pay_time' => time(),
            'end_time' => time() + $order['period'] * 86400,
        ]);

            // //购买产品和恢复资产用户激活
            // if ($order['user']['is_active'] == 0 ) {
            //     User::where('id', $order['user_id'])->update(['is_active' => 1, 'active_time' => time()]);
            //     // 下级用户激活
            //     UserRelation::where('sub_user_id', $order['user_id'])->update(['is_active' => 1]);
            // }

            User::where('id',$user_id)->inc('invest_amount',$order['price'])->update();
            //判断是否活动时间内记录活动累计消费 4.30-5.6
            // User::where('id',$user_id)->inc('huodong',1)->update();
            // User::upLevel($user_id);
        return true;
    }
}

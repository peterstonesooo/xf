<?php

namespace app\model;

use think\Model;

class PointsOrder extends Model
{
    protected $name = 'points_orders';
    
    const ORDER_STATUS = [
        1 => '待发货',
        2 => '已发货',
        3 => '已签收'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(PointsProduct::class, 'product_id');
    }

    public function delivery()
    {
        return $this->belongsTo(UserDelivery::class, 'delivery_id');
    }

    // 创建订单
    public static function createOrder($userId, $productId, $pointsUsed, $quantity)
    {
        return self::create([
            'user_id' => $userId,
            'product_id' => $productId,
            'points_used' => $pointsUsed,
            'number' => $quantity,
            'order_status' => 1
        ]);
    }

} 
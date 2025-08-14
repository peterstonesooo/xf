<?php

namespace app\model;

use think\Model;

class PointsProduct extends Model
{
    protected $name = 'points_only_products';
    
    const PRODUCT_STATUS = [
        1 => '上架',
        0 => '下架'
    ];

    // 更新库存
    public static function updateStock($productId, $quantity)
    {
        return self::where('id', $productId)
            ->where('stock_quantity', '>=', $quantity)
            ->dec('stock_quantity', $quantity)
            ->update();
    }
} 
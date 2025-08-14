<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class PointsOnlyProduct extends Model
{
    protected $name = 'points_only_products';
    protected $autoWriteTimestamp = true;
    
    protected $schema = [
        'id' => 'int',
        'product_name' => 'string',
        'product_description' => 'string',
        'points_price' => 'int',
        'stock_quantity' => 'int',
        'product_image_url' => 'string',
        'product_status' => 'int',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp'
    ];
} 
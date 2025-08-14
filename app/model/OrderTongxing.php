<?php

namespace app\model;

use think\Model;

class OrderTongxing extends Model
{
    protected $name = 'order_tongxing';
    protected $pk = 'id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'up_user_id' => 'integer',
        'user_id' => 'integer',
        'status' => 'integer',
        'project_id' => 'integer',
        'pay_method' => 'integer',
        'pay_time' => 'integer',
        'is_admin_confirm' => 'integer',
        'single_amount' => 'float',
        'price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // 字段默认值
    protected $default = [
        'up_user_id' => 0,
        'user_id' => 0,
        'order_sn' => '',
        'status' => 1,
        'project_id' => 0,
        'project_name' => '',
        'single_amount' => 0.00,
        'pay_method' => 0,
        'pay_time' => 0,
        'is_admin_confirm' => 0,
        'price' => 0.00,
    ];
    
    // 状态映射
    public static $statusMap = [
        1 => '待支付',
        2 => '收益中',
        3 => '待出售',
        4 => '已完成'
    ];
    
    // 支付方式映射
    public static $payMethodMap = [
        1 => '余额',
        2 => '微信',
        3 => '支付宝',
        4 => '银联',
        5 => '积分兑换'
    ];
} 
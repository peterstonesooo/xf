<?php

namespace app\model;

use think\Model;

/**
 * 黄金交易订单模型
 */
class GoldOrder extends Model
{
    protected $name = 'gold_order';
    
    // 设置字段信息
    protected $schema = [
        'id'                => 'bigint',
        'order_no'          => 'string',
        'user_id'           => 'bigint',
        'type'              => 'int',
        'quantity'          => 'decimal',
        'price'             => 'decimal',
        'amount'            => 'decimal',
        'fee'               => 'decimal',
        'fee_rate'          => 'decimal',
        'actual_amount'     => 'decimal',
        'cost_price_before' => 'decimal',
        'cost_price_after'  => 'decimal',
        'balance_before'    => 'decimal',
        'balance_after'     => 'decimal',
        'profit'            => 'decimal',
        'status'            => 'int',
        'remark'            => 'string',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    /**
     * 交易类型常量
     */
    const TYPE_BUY = 1;     // 买入
    const TYPE_SELL = 2;    // 卖出
    const TYPE_REWARD = 3;  // 系统奖励
    
    /**
     * 订单状态常量
     */
    const STATUS_CANCELLED = 0;  // 已取消
    const STATUS_COMPLETED = 1;  // 已完成
    const STATUS_PROCESSING = 2; // 处理中
    
    /**
     * 获取类型文本
     */
    public function getTypeTextAttr($value, $data)
    {
        $map = [
            self::TYPE_BUY => '买入',
            self::TYPE_SELL => '卖出',
            self::TYPE_REWARD => '系统奖励',
        ];
        return $map[$data['type']] ?? '未知';
    }
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $map = [
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_PROCESSING => '处理中',
        ];
        return $map[$data['status']] ?? '未知';
    }
}


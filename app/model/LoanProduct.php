<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class LoanProduct extends Model
{
    protected $name = 'loan_product';
    protected $pk = 'id';
    
    // 允许写入的字段
    protected $allowField = ['name', 'min_amount', 'max_amount', 'interest_type', 'overdue_interest_rate', 'max_overdue_days', 'status', 'sort'];

    // 状态映射
    public static $statusMap = [
        0 => '禁用',
        1 => '启用'
    ];

    // 利息类型映射
    public static $interestTypeMap = [
        1 => '日利息',
        2 => '年利息'
    ];

    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        return self::$statusMap[$data['status']] ?? '未知';
    }

    // 获取利息类型文本
    public function getInterestTypeTextAttr($value, $data)
    {
        return self::$interestTypeMap[$data['interest_type']] ?? '未知';
    }

    // 关联梯度
    public function gradients()
    {
        return $this->hasMany(LoanProductGradient::class, 'product_id', 'id');
    }

    // 关联贷款申请
    public function applications()
    {
        return $this->hasMany(LoanApplication::class, 'product_id', 'id');
    }
}

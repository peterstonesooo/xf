<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class LoanApplication extends Model
{
    protected $name = 'loan_application';
    protected $pk = 'id';

    // 状态映射
    public static $statusMap = [
        1 => '待审核',
        2 => '已通过',
        3 => '已拒绝',
        4 => '已放款',
        5 => '已结清'
    ];

    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        return self::$statusMap[$data['status']] ?? '未知';
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // 关联产品
    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'product_id', 'id');
    }

    // 关联梯度
    public function gradient()
    {
        return $this->belongsTo(LoanProductGradient::class, 'gradient_id', 'id');
    }

    // 关联还款计划
    public function repaymentPlans()
    {
        return $this->hasMany(LoanRepaymentPlan::class, 'application_id', 'id');
    }

    // 关联审核人
    public function auditUser()
    {
        return $this->belongsTo(User::class, 'audit_user_id', 'id');
    }
}


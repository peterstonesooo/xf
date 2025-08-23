<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class LoanRepaymentPlan extends Model
{
    protected $name = 'loan_repayment_plan';
    protected $pk = 'id';

    // 状态映射
    public static $statusMap = [
        1 => '待还款',
        2 => '已还款',
        3 => '逾期'
    ];

    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        return self::$statusMap[$data['status']] ?? '未知';
    }

    // 关联贷款申请
    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'application_id', 'id');
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // 关联还款记录
    public function repaymentRecords()
    {
        return $this->hasMany(LoanRepaymentRecord::class, 'plan_id', 'id');
    }
}

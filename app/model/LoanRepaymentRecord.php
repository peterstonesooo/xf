<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class LoanRepaymentRecord extends Model
{
    protected $name = 'loan_repayment_record';
    protected $pk = 'id';

    // 还款类型映射
    public static $repaymentTypeMap = [
        1 => '正常还款',
        2 => '提前还款',
        3 => '逾期还款'
    ];

    // 还款方式映射
    public static $repaymentMethodMap = [
        1 => '自动扣款',
        2 => '手动还款'
    ];

    // 获取还款类型文本
    public function getRepaymentTypeTextAttr($value, $data)
    {
        return self::$repaymentTypeMap[$data['repayment_type']] ?? '未知';
    }

    // 获取还款方式文本
    public function getRepaymentMethodTextAttr($value, $data)
    {
        return self::$repaymentMethodMap[$data['repayment_method']] ?? '未知';
    }

    // 关联还款计划
    public function plan()
    {
        return $this->belongsTo(LoanRepaymentPlan::class, 'plan_id', 'id');
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
}


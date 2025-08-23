<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class LoanProductGradient extends Model
{
    protected $name = 'loan_product_gradient';
    protected $pk = 'id';

    // 状态映射
    public static $statusMap = [
        0 => '禁用',
        1 => '启用'
    ];

    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        return self::$statusMap[$data['status']] ?? '未知';
    }

    // 关联产品
    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'product_id', 'id');
    }
}

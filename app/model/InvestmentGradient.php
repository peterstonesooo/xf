<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class InvestmentGradient extends Model
{
    protected $name = 'investment_gradient';
    protected $pk = 'id';
    
    // 允许写入的字段
    protected $allowField = ['name', 'investment_days', 'interest_rate', 'min_amount', 'max_amount', 'status', 'sort'];

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

    // 关联出资记录
    public function investments()
    {
        return $this->hasMany(InvestmentRecord::class, 'gradient_id', 'id');
    }
}

<?php

namespace app\model;

use think\Model;

class ExclusiveLog extends Model
{
    protected $table = 'mp_exclusive_log';
    
    // 状态映射
    public function getStatusTextAttr($value, $data)
    {
        $map = [
            0 => '处理中',
            1 => '已完成', 
            2 => '拒绝'
        ];
        return $map[$data['status']] ?? '未知';
    }
    
    
}
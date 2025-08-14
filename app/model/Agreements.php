<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Agreements extends Model
{
    
    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'type'            => 'int',
        'version'         => 'string',
        'content'         => 'string',
        'status'          => 'int',
        'effective_date'  => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    // 类型转换
    protected $type = [
        'effective_date' => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // 获取最新的协议
    public static function getLatest($type)
    {
        return self::where('type', $type)
            ->order('effective_date', 'desc')
            ->find();
    }
} 
<?php

namespace app\model;

use think\Model;

class ProjectTongxing extends Model
{
    protected $name = 'project_tongxing';
    protected $pk = 'id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'creat_at';
    protected $updateTime = 'updated_at';
    
    // JSON字段
    protected $json = ['amounts', 'cover_img'];
    protected $jsonAssoc = true;
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'creat_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // 字段默认值
    protected $default = [
        'name' => '',
        'intro' => '',
        'cover_img' => [],
        'details_img' => '',
        'video_url' => '',
        'amounts' => [],
        'help_province' => 0,
        'help_city' => 0,
        'help_district' => 0,
        'fund_goal' => 0.00,
        'already_fund' => 0.00,
        'support_numbers' => 0,
    ];
} 
<?php

namespace app\model;

use think\Model;

class ProjectTongxingZhixing extends Model
{
    protected $name = 'project_tongxing_zhixing';
    protected $pk = 'id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'creat_at';
    protected $updateTime = 'updated_at';
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'order' => 'integer',
        'creat_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // 字段默认值
    protected $default = [
        'title' => '',
        'detial' => '',
        'cover_img' => '',
        'city' => '',
        'order' => 0,
    ];
}


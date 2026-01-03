<?php

namespace app\model;

use think\Model;

class PeopleLivelihoodInfo extends Model
{
    protected $name = 'people_livelihood_info';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}


<?php

namespace app\model;

use think\Model;

class VipLog extends Model
{
    protected $name = 'vip_log';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $type = [
        'pay_amount' => 'float',
        'status' => 'integer',
        'pay_time' => 'integer',
    ];
}


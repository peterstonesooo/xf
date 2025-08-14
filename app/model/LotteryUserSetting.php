<?php

namespace app\model;

use think\Model;

class LotteryUserSetting extends Model
{
    protected $name = 'lottery_user_setting';
    
    public function getStatusTextAttr($value, $data)
    {
        $map = [0 => '未抽取', 1 => '已抽取'];
        return $map[$data['status']] ?? '';
    }
} 
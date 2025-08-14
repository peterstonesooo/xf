<?php
namespace app\model;

use think\Model;

class LotterySetting extends Model
{
    protected $name = 'lottery_setting';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
} 
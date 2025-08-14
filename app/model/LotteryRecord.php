<?php
namespace app\model;

use think\Model;

class LotteryRecord extends Model
{
    protected $name = 'lottery_record';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'lottery_time';
    protected $updateTime = false;
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'order_id'    => 'int',
        'user_id'     => 'int',
        'lottery_time'=> 'datetime',
        'lottery_result' => 'string',
        'points_used' => 'int',
        'receive'     => 'tinyint',
        'lottery_id'  => 'int',
    ];

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 关联奖品
    public function lottery()
    {
        return $this->belongsTo(LotterySetting::class, 'lottery_id');
    }
} 
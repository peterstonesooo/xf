<?php

namespace app\model;

use think\Model;

class VoteRecord extends Model
{
    protected $table = 'mp_vote_record';
    
    // 设置字段信息
    protected $schema = [
        'id'                => 'int',
        'vote_id'           => 'int',
        'uid'               => 'int',
        'phone'             => 'string',
        'realname'          => 'string',
        'selected_options'  => 'string',
        'ip_address'        => 'string',
        'user_agent'        => 'string',
        'create_time'       => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;

    // JSON字段
    protected $json = ['selected_options'];
    
    // 获取器 - 确保selected_options返回数组
    public function getSelectedOptionsAttr($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_object($value)) {
            return (array) $value;
        }
        return is_array($value) ? $value : [];
    }

    // 关联投票
    public function vote()
    {
        return $this->belongsTo(\app\model\Vote::class, 'vote_id', 'id');
    }
}

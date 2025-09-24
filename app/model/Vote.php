<?php

namespace app\model;

use think\Model;

class Vote extends Model
{
    protected $table = 'mp_vote';
    
    // 设置字段信息
    protected $schema = [
        'id'            => 'int',
        'uid'           => 'int',
        'phone'         => 'string',
        'realname'      => 'string',
        'title'         => 'string',
        'content'       => 'string',
        'vote_type'     => 'int',
        'options'       => 'string',
        'status'        => 'int',
        'is_anonymous'  => 'int',
        'max_votes'     => 'int',
        'start_time'    => 'datetime',
        'end_time'      => 'datetime',
        'total_votes'   => 'int',
        'view_count'    => 'int',
        'is_deleted'    => 'int',
        'create_time'   => 'datetime',
        'update_time'   => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // JSON字段
    protected $json = ['options'];
    
    // 获取器 - 确保options返回数组
    public function getOptionsAttr($value)
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

    // 关联投票记录
    public function voteRecords()
    {
        return $this->hasMany(\app\model\VoteRecord::class, 'vote_id', 'id');
    }

    // 检查用户是否已报名
    public static function isUserRegistered($uid)
    {
        return self::where('uid', $uid)->where('is_deleted', 0)->find() ? true : false;
    }

    // 检查用户投票票数是否足够
    public static function hasEnoughTickets($uid, $requiredTickets = 1)
    {
        $user = \app\model\User::find($uid);
        return $user && isset($user->vote_tickets) && $user->vote_tickets >= $requiredTickets;
    }

    // 检查用户是否已投票
    public static function isUserVoted($voteId, $uid)
    {
        return \app\model\VoteRecord::where('vote_id', $voteId)
            ->where('uid', $uid)
            ->find() ? true : false;
    }
}

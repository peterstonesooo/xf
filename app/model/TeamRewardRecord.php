<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class TeamRewardRecord extends Model
{
    protected $name = 'team_reward_record';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'sub_user_id' => 'integer',
        'reward_level' => 'integer',
        'reward_amount' => 'float',
        'status' => 'integer',
    ];
    
    /**
     * 检查用户是否已领取指定级别的奖励
     */
    public static function hasReceivedReward($subUserId, $rewardLevel)
    {
        return self::where('sub_user_id', $subUserId)
            ->where('reward_level', $rewardLevel)
            ->where('status', 1)
            ->find();
    }
    
    /**
     * 获取用户的奖励记录
     */
    public static function getUserRewards($subUserId)
    {
        return self::where('sub_user_id', $subUserId)
            ->where('status', 1)
            ->order('reward_level', 'asc')
            ->select();
    }
    
    /**
     * 获取发放者的发放记录
     */
    public static function getIssuerRewards($userId)
    {
        return self::where('user_id', $userId)
            ->where('status', 1)
            ->with(['subUser'])
            ->order('created_at', 'desc')
            ->select();
    }
    
    /**
     * 关联下级用户
     */
    public function subUser()
    {
        return $this->belongsTo(User::class, 'sub_user_id', 'id');
    }
    
    /**
     * 关联发放者
     */
    public function issuer()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}


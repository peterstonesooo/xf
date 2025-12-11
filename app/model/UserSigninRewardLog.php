<?php

namespace app\model;

use think\Model;

class UserSigninRewardLog extends Model
{
    protected $table = 'mp_user_signin_reward_log';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 签到天数映射
    public static $rewardDaysMap = [
        1 => 7,   // type=1 对应连续7天
        2 => 15,  // type=2 对应连续15天
        3 => 30,  // type=3 对应连续30天
    ];
    
    /**
     * 根据type获取签到天数
     * @param int $type 类型：1-7天，2-15天，3-30天
     * @return int|null
     */
    public static function getRewardDaysByType($type)
    {
        return self::$rewardDaysMap[$type] ?? null;
    }
    
    /**
     * 获取签到天数文本
     * @param int $days
     * @return string
     */
    public static function getRewardDaysText($days)
    {
        $textMap = [
            7 => '连续7天',
            15 => '连续15天',
            30 => '连续30天',
        ];
        return $textMap[$days] ?? '未知';
    }
    
    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

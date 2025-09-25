<?php

namespace app\model;

use think\Model;
use think\facade\Db;

class UserActive extends Model
{
    protected $name = 'user_active';
    protected $pk = 'id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = false;
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'is_active' => 'integer',
        'active_time' => 'integer',
    ];
    
    // 字段默认值
    protected $default = [
        'user_id' => 0,
        'is_active' => 0,
        'active_time' => 0,
    ];
    
    /**
     * 检查用户是否已激活
     */
    public static function isUserActive($userId)
    {
        return self::where('user_id', $userId)
                   ->where('is_active', 1)
                   ->find();
    }
    
    /**
     * 激活用户
     */
    public static function activateUser($userId, $upUserId = 0)
    {
        // 检查用户是否已经激活
        $existing = self::where('user_id', $userId)->find();
        
        if ($existing) {
            // 如果已经激活，不重复操作
            return $existing;
        }
        
        // 创建激活记录
        return self::create([
            'user_id' => $userId,
            'is_active' => 1,
            'active_time' => time()
        ]);
    }
    
    /**
     * 获取用户的激活记录
     */
    public static function getUserActivation($userId)
    {
        return self::where('user_id', $userId)->find();
    }
}

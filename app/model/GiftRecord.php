<?php

namespace app\model;

use think\Model;

class GiftRecord extends Model
{
    protected $name = 'gift_record';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'user_id'     => 'int',
        'project_id'  => 'int',
        'project_name'=> 'string',
        'order_sn'    => 'string',
        'gift_amount' => 'decimal',
        'admin_user_id' => 'int',
        'created_at'  => 'datetime',
    ];

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 关联项目
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    // 关联管理员
    public function adminUser()
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    /**
     * 获取用户赠送次数
     */
    public static function getUserGiftCount($user_id)
    {
        return self::where('user_id', $user_id)->count();
    }

    /**
     * 检查用户是否可以赠送产品
     */
    public static function canGift($user_id)
    {
        $giftCount = self::getUserGiftCount($user_id);
        $completedGroups = UserProjectGroup::getUserCompletedGroups($user_id);
        
        // 赠送次数不能超过完成的产品组数量
        return $giftCount < $completedGroups;
    }

    /**
     * 获取用户可赠送次数
     */
    public static function getAvailableGiftCount($user_id)
    {
        $giftCount = self::getUserGiftCount($user_id);
        $completedGroups = UserProjectGroup::getUserCompletedGroups($user_id);
        
        return max(0, $completedGroups - $giftCount);
    }
}

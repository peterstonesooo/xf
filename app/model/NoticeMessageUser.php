<?php
namespace app\model;

use think\Model;

class NoticeMessageUser extends Model
{
    protected $name = 'notice_message_user';
    
    // 定义已读状态
    const STATUS_UNREAD = 0; // 未读
    const STATUS_READ = 1;   // 已读
    
    // 关联消息
    public function message()
    {
        return $this->belongsTo(NoticeMessage::class, 'message_id', 'id');
    }
    
    // 获取已读状态文本
    public function getIsReadTextAttr($value, $data)
    {
        $status = [
            self::STATUS_UNREAD => '未读',
            self::STATUS_READ => '已读'
        ];
        return isset($status[$data['is_read']]) ? $status[$data['is_read']] : '';
    }
} 
<?php
namespace app\model;

use think\Model;

class NoticeMessage extends Model
{
    protected $name = 'notice_message';
    
    // 定义消息类型
    const TYPE_SYSTEM = 1;    // 系统通知
    const TYPE_IMPORTANT = 2; // 重要公告
    const TYPE_ACTIVITY = 3;  // 活动消息
    
    // 获取消息类型列表
    public static function getTypeList()
    {
        return [
            self::TYPE_SYSTEM => '系统通知',
            self::TYPE_IMPORTANT => '重要公告',
            self::TYPE_ACTIVITY => '活动消息'
        ];
    }
    
    // 获取消息类型文本
    public function getTypeTextAttr($value, $data)
    {
        $types = self::getTypeList();
        return isset($types[$data['type']]) ? $types[$data['type']] : '';
    }
    
    /**
     * 发送系统通知
     * @param string $title 消息标题
     * @param string $content 消息内容
     * @param int|array $userIds 接收用户ID，可以是单个ID或ID数组
     * @return bool
     */
    public static function sendSystemNotice($title, $content, $userIds)
    {
        try {
            // 确保userIds是数组
            if (!is_array($userIds)) {
                $userIds = [$userIds];
            }
            
            $data = [];
            // 创建消息
            $message = self::create([
                'title' => $title,
                'content' => $content,
                'type' => self::TYPE_SYSTEM,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            // 为每个用户创建消息记录
            $data = [];
            foreach ($userIds as $userId) {
                $data[] = [
                    'user_id' => $userId,
                    'message_id' => $message->id,
                    'is_read' => 0,
                    'read_time' => null
                ];
            }
            
            // 批量插入数据
            return NoticeMessageUser::insertAll($data);
        } catch (\Exception $e) {
            // 记录错误日志
            \think\facade\Log::error('发送系统通知失败：' . $e->getMessage());
            return false;
        }
    }
} 
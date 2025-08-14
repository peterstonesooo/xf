<?php
namespace app\api\controller;

use app\model\NoticeMessage;
use app\model\NoticeMessageUser;
use think\facade\Db;

class NoticeMessageController extends AuthController
{
    /**
     * 获取用户消息列表
     * @return \think\response\Json
     */
    public function getUserMessages()
    {
        $user = $this->user;
        $userId = $user['id'];
        try {
            $messages = NoticeMessageUser::with(['message'])
                ->where('user_id', $userId)
                ->order('id', 'desc')
                ->select()
                ->each(function($item) {
                    $item['type_text'] = $item->message->type_text;
                    $item['is_read_text'] = $item->is_read_text;
                    return $item;
                });

            return out($messages, 0, '获取成功');
        } catch (\Exception $e) {
            return out(null, 10002, '获取消息失败：' . $e->getMessage());
        }
    }

    public function readUserMessage()
    {
        $req = $this->validate(request(), [
            'message_id|ID' => 'require|number',
        ]);
        $user = $this->user;
        $userId = $user['id'];
        $msg = NoticeMessage::where('id', $req['message_id'])->find();
        if(!$msg){
            return out(null, 10001, '消息不存在');
        }
        $has = NoticeMessageUser::where('message_id', $req['message_id'])->where('user_id', $userId)->find();
        if(!$has){
            return out(null, 10001, '消息不存在');
        }
        NoticeMessageUser::where('message_id', $req['message_id'])->where('user_id', $userId)->update(['is_read' => 1]);
        return out($msg);
    }
} 
<?php

namespace app\admin\controller;

use app\model\NoticeMessage;
use app\model\NoticeMessageUser;
use app\model\User;
use think\facade\Db;

class NoticeController extends AuthController
{
    public function messageList()
    {
        $req = request()->param();
        $builder = NoticeMessage::order('id', 'desc');
        
        if (isset($req['title']) && $req['title'] !== '') {
            $builder->where('title', 'like', "%{$req['title']}%");
        }
        if (isset($req['type']) && $req['type'] !== '') {
            $builder->where('type', $req['type']);
        }
        
        $data = $builder->paginate(['query' => $req]);
        $this->assign('req', $req);
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function showMessage()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = NoticeMessage::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function addMessage()
    {
        $req = $this->validate(request(), [
            'title|标题' => 'require|max:255',
            'content|内容' => 'require',
            'type|类型' => 'require|in:1,2,3'
        ]);

        NoticeMessage::create($req);
        return out();
    }

    public function editMessage()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'title|标题' => 'require|max:255',
            'content|内容' => 'require',
            'type|类型' => 'require|in:1,2,3'
        ]);

        NoticeMessage::where('id', $req['id'])->update($req);
        return out();
    }

    public function delMessage()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        Db::startTrans();
        try {
            // 删除消息
            NoticeMessage::destroy($req['id']);
            // 删除关联的用户记录
            NoticeMessageUser::where('message_id', $req['id'])->delete();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    public function sendMessage()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'send_type' => 'require|in:1,2', // 1:所有用户 2:指定用户
            'phones' => 'requireIf:send_type,2|max:1000' // 指定用户时必填
        ]);

        $message = NoticeMessage::find($req['id']);
        if (!$message) {
            return out(null, 10001, '消息不存在');
        }

        Db::startTrans();
        try {
            if ($req['send_type'] == 1) {
                // 发送给所有用户
                $userIds = User::where('status', 1)->column('id');
            } else {
                // 发送给指定用户
                $phones = explode(',', $req['phones']);
                $userIds = User::where('status', 1)
                    ->whereIn('phone', $phones)
                    ->column('id');
            }

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
            
            // 批量插入消息记录
            NoticeMessageUser::insertAll($data);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
} 
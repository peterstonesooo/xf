<?php

namespace app\admin\controller;

use app\model\SystemInfo;

class ConferenceController extends AuthController
{
    // 会议中心列表
    public function conferenceList()
    {
        $req = request()->param();

        $builder = SystemInfo::order('id', 'desc')->where('type', 15);
        if (isset($req['conference_id']) && $req['conference_id'] !== '') {
            $builder->where('id', $req['conference_id']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    // 显示添加/编辑会议中心页面
    public function showConference()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = SystemInfo::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    // 添加会议中心
    public function addConference()
    {
        $req = $this->validate(request(), [
            'title|标题' => 'require|max:100',
            'link|官方链接' => 'max:255',
            'content|内容' => 'require',
            'sort|排序号' => 'integer',
            'created_at|创建时间' => 'date',
        ]);

        $req['type'] = 15; // 会议中心类型固定为15
        $req['cover_img'] = upload_file('cover_img', false);
        if(!isset($req['content']))
            $req['content'] = '';

        SystemInfo::create($req);

        return out();
    }

    // 编辑会议中心
    public function editConference()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'title|标题' => 'require|max:100',
            'link|官方链接' => 'max:255',
            'content|内容' => 'require',
            'sort|排序号' => 'integer',
            'created_at|创建时间' => 'date',
        ]);
        
        $req['type'] = 15; // 会议中心类型固定为15
        if ($cover_img = upload_file('cover_img', false)) {
            $req['cover_img'] = $cover_img;
        }
        
        SystemInfo::where('id', $req['id'])->update($req);

        return out();
    }

    // 修改会议中心状态
    public function changeConference()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        SystemInfo::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    // 删除会议中心
    public function delConference()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        SystemInfo::destroy($req['id']);

        return out();
    }
}


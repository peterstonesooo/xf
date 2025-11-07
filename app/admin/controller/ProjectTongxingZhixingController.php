<?php

namespace app\admin\controller;

use app\model\ProjectTongxingZhixing;
use think\facade\Cache;

class ProjectTongxingZhixingController extends AuthController
{
    public function zhixingList()
    {
        $req = request()->param();

        $builder = ProjectTongxingZhixing::order(['order' => 'asc', 'id' => 'desc']);
        
        if (isset($req['id']) && $req['id'] !== '') {
            $builder->where('id', $req['id']);
        }
        if (isset($req['title']) && $req['title'] !== '') {
            $builder->where('title', 'like', '%' . $req['title'] . '%');
        }
        
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showZhixing()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = ProjectTongxingZhixing::where('id', $req['id'])->find();
        }
        
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addZhixing()
    {
        $req = $this->validate(request(), [
            'title|名称' => 'require|max:32',
            'city|帮助市' => 'max:255',
            'order|排序' => 'number',
        ]);
        
        if(Cache::get('project_tongxing_zhixing_addZhixing')){
            return out(null, 10001, '操作过于频繁，请稍后再试');
        }
        Cache::set('project_tongxing_zhixing_addZhixing', 1, 5);
        
        // 处理简介（富文本内容）
        $req['detial'] = request()->param('detial', '');
        $creatAtParam = request()->param('creat_at', '');
        if ($creatAtParam !== '') {
            $creatAtTimestamp = strtotime($creatAtParam);
            if ($creatAtTimestamp === false) {
                return out(null, 10001, '创建时间格式不正确');
            }
            $req['creat_at'] = date('Y-m-d H:i:s', $creatAtTimestamp);
        }
        
        // 处理封面图（单图）
        $req['cover_img'] = upload_file('cover_img');
        
        $zhixing = ProjectTongxingZhixing::create($req);

        return out(['id' => $zhixing->id]);
    }

    public function editZhixing()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'title|名称' => 'require|max:32',
            'city|帮助市' => 'max:255',
            'order|排序' => 'number',
        ]);
        
        // 处理简介（富文本内容）
        $req['detial'] = request()->param('detial', '');
        $creatAtParam = request()->param('creat_at', '');
        if ($creatAtParam !== '') {
            $creatAtTimestamp = strtotime($creatAtParam);
            if ($creatAtTimestamp === false) {
                return out(null, 10001, '创建时间格式不正确');
            }
            $req['creat_at'] = date('Y-m-d H:i:s', $creatAtTimestamp);
        }
        
        // 处理封面图（单图）
        if ($img = upload_file('cover_img', false, false)) {
            $req['cover_img'] = $img;
        }
        
        ProjectTongxingZhixing::where('id', $req['id'])->update($req);

        return out(['id' => $req['id']]);
    }

    public function delZhixing()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        ProjectTongxingZhixing::destroy($req['id']);

        return out();
    }
}


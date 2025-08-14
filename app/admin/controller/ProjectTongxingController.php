<?php

namespace app\admin\controller;

use app\model\ProjectTongxing;
use think\facade\Cache;

class ProjectTongxingController extends AuthController
{
    public function projectList()
    {
        $req = request()->param();

        $builder = ProjectTongxing::order(['id' => 'desc']);
        
        if (isset($req['id']) && $req['id'] !== '') {
            $builder->where('id', $req['id']);
        }
        if (isset($req['name']) && $req['name'] !== '') {
            $builder->where('name', 'like', '%' . $req['name'] . '%');
        }
        
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showProject()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = ProjectTongxing::where('id', $req['id'])->find();
            if ($data && !empty($data['amounts'])) {
                $data['amounts'] = is_array($data['amounts']) ? $data['amounts'] : json_decode($data['amounts'], true);
            }
        }
        
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addProject()
    {
        $req = $this->validate(request(), [
            'name|名称' => 'require|max:32',
            'intro|简介' => 'max:255',
            'video_url|视频链接' => 'max:255',
        ]);
        
        if(Cache::get('project_tongxing_addProject')){
            return out(null, 10001, '操作过于频繁，请稍后再试');
        }
        Cache::set('project_tongxing_addProject', 1, 5);
        
        // 处理金额、说明数据
        $amounts = [];
        $amount_values = request()->param('amount_value/a', []);
        $amount_descriptions = request()->param('amount_description/a', []);
        
        for ($i = 0; $i < count($amount_values); $i++) {
            if (!empty($amount_values[$i])) {
                $amounts[] = [
                    'value' => $amount_values[$i],
                    'description' => $amount_descriptions[$i] ?? ''
                ];
            }
        }
        
        $req['amounts'] = $amounts;
        $req['cover_img'] = upload_file('cover_img');
        $req['details_img'] = upload_file('details_img');
        
        $project = ProjectTongxing::create($req);

        return out(['id' => $project->id]);
    }

    public function editProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'name|名称' => 'require|max:32',
            'intro|简介' => 'max:255',
            'video_url|视频链接' => 'max:255',
        ]);
        
        // 处理金额、说明数据
        $amounts = [];
        $amount_values = request()->param('amount_value/a', []);
        $amount_descriptions = request()->param('amount_description/a', []);
        
        for ($i = 0; $i < count($amount_values); $i++) {
            if (!empty($amount_values[$i])) {
                $amounts[] = [
                    'value' => $amount_values[$i],
                    'description' => $amount_descriptions[$i] ?? ''
                ];
            }
        }
        
        $req['amounts'] = $amounts;
        
        if ($img = upload_file('cover_img', false, false)) {
            $req['cover_img'] = $img;
        }
        if ($img = upload_file('details_img', false, false)) {
            $req['details_img'] = $img;
        }
        
        ProjectTongxing::where('id', $req['id'])->update($req);

        return out(['id' => $req['id']]);
    }

    public function delProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        ProjectTongxing::destroy($req['id']);

        return out();
    }
}

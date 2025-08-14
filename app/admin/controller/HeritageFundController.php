<?php

namespace app\admin\controller;

use app\model\Project;
use think\facade\Db;

class HeritageFundController extends AuthController
{
    public function index()
    {
        $data = Project::where('project_group_id', 17)->find();
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function save()
    {
        $project = Project::where('project_group_id', 17)->find();
        
        $req = $this->validate(request(), [
            'name' => 'require|max:255',
            'single_amount' => 'require|float|gt:0',
            'period' => 'require|integer|gt:0',
            'sham_buy_num' => 'require|integer|gt:0',
            'total_num' => 'require|integer|gt:0',
            'dividend_cycle' => 'require|integer|gt:0',
            'sum_amount' => 'require|float|gt:0'

        ]);

    
        Db::startTrans();
        try {
            if ($project) {
                Project::where('id', $project['id'])->update($req);
            } else {
                // 新增
                $req['project_group_id'] = 17;
                $req['details_img'] = ''; // Set default empty value for details_img
                Project::create($req);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
} 
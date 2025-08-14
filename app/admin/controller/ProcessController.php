<?php

namespace app\admin\controller;

use app\model\ProcessReview;

class ProcessController extends AuthController
{
    public function ProcessList()
    {
        $req = request()->param();
        $p = ProcessReview::order('id', 'asc');
        if(!empty($req['id']) && $req['id'] !== ''){
            $p->where('id',$req['id']);
        }
        $data = $p->select();
        // $data = $p->paginate(['query' => $req]);
        // print_r($data);die;
        $this->assign('req', $req);
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function showProcess()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = ProcessReview::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addProcess()
    {
        $req = $this->validate(request(), [
            'type|所属流程' => 'integer',
            'sort|排序号' => 'integer',
            'number|天数' => 'integer',
            'name|流程名' => 'require',
        ]);
        ProcessReview::create($req);
        return out();
    }

    public function editProcess()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'type|所属流程' => 'integer',
            'sort|排序号' => 'integer',
            'number|天数' => 'integer',
            'name|流程名' => 'require',
        ]);
        ProcessReview::where('id', $req['id'])->update($req);
        return out();
    }

    public function delProcess()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        if($req['id'] != 1 && $req['id'] != 2 && $req['id'] != 3 && $req['id'] != 4){
            ProcessReview::destroy($req['id']);
        }
        
        return out();
    }
}
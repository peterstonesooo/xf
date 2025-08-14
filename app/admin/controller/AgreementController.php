<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\model\Agreements as AgreementModel;
use think\facade\View;

class AgreementController extends AuthController
{
    // 协议列表
    public function index()
    {
        $req = $this->request->param();
        $where = [];
        
        // 协议类型筛选
        if (!empty($req['type'])) {
            $where[] = ['type', '=', $req['type']];
        }
        
        // 状态筛选
        if (isset($req['status']) && $req['status'] !== '') {
            $where[] = ['status', '=', $req['status']];
        }
        
        // 获取数据
        $data = AgreementModel::where($where)
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => 10,
                'query' => $req
            ]);
            
        View::assign([
            'data' => $data,
            'req' => $req
        ]);
        
        return View::fetch();
    }

    // 添加协议
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data['effective_date'] = date('Y-m-d H:i:s',time());
            
            try {
                $agreement = new AgreementModel;
                $agreement->save($data);
                return json(['code' => 0, 'msg' => '添加成功']);
            } catch (\Exception $e) {
                return json(['code' => 1, 'msg' => '添加失败：' . $e->getMessage()]);
            }
        }
        
        return View::fetch();
    }

    // 编辑协议
    public function edit($id)
    {
        $data = AgreementModel::find($id);
        if (!$data) {
            return $this->error('协议不存在');
        }

        if ($this->request->isPost()) {
            $post = $this->request->post();
            // $post['effective_date'] = strtotime($post['effective_date']);
            
            try {
                $data->save($post);
                return json(['code' => 0, 'msg' => '更新成功']);
            } catch (\Exception $e) {
                return json(['code' => 1, 'msg' => '更新失败：' . $e->getMessage()]);
            }
        }

        View::assign('data', $data);
        return View::fetch();
    }

    // 删除协议
    public function delete($id)
    {
        try {
            AgreementModel::destroy($id);
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '删除失败：' . $e->getMessage()]);
        }
    }

    // 修改状态
    public function status()
    {
        $req = $this->request->post();
        if (empty($req['id']) || !isset($req['status'])) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        try {
            $agreement = AgreementModel::find($req['id']);
            if (!$agreement) {
                return json(['code' => 1, 'msg' => '协议不存在']);
            }

            $agreement->status = $req['status'];
            $agreement->save();
            return json(['code' => 0, 'msg' => '状态修改成功']);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '状态修改失败：' . $e->getMessage()]);
        }
    }
} 
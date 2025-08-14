<?php

namespace app\admin\controller;

use app\model\UserDelivery;

class UserDeliveryController extends AuthController
{
    public function userDeliveryList()
    {
        $req = request()->param();

        $builder = UserDelivery::order('id', 'desc');
        if (isset($req['user_delivery_id']) && $req['user_delivery_id'] !== '') {
            $builder->where('id', $req['user_delivery_id']);
        }
        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('user_id', $req['user_id']);
        }

        if (!empty($req['export'])) {
            $list = $builder->select();
            if ($req['export'] == '地址导出') {

                create_excel($list, ['user_id' => '用户id', 'name' => '实名', 'phone' => '手机号', 'address' => '地址'],'地址列表-'.date('YmdHis'));
            }
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showUserDelivery()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = UserDelivery::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addUserDelivery()
    {
        $req = $this->validate(request(), [
            'user_id|用户ID' => 'require|number',
            'name|收货人名称' => 'require|max:50',
            'phone|手机号' => 'require|mobile',
            'address|详细地址' => 'require|max:250',
        ]);

        UserDelivery::create($req);

        return out();
    }

    public function editUserDelivery()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'name|收货人名称' => 'require|max:50',
            'phone|手机号' => 'require|mobile',
            'address|详细地址' => 'require|max:250',
        ]);

        UserDelivery::where('id', $req['id'])->update($req);

        return out();
    }

    public function delUserDelivery()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        UserDelivery::destroy($req['id']);

        return out();
    }
}

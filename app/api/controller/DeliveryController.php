<?php

namespace app\api\controller;

use app\model\UserDelivery;

class DeliveryController extends AuthController
{
    public function userDeliveryList()
    {
        $user = $this->user;
        $data = UserDelivery::where('user_id', $user['id'])->paginate();
        return out($data);
    }

    public function userDeliveryDetail()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $data = UserDelivery::where('user_id', $user['id'])->where('id',$req['id'])->find();

        return out($data);
    }

    public function saveDelivery()
    {
        $req = $this->validate(request(), [
            //'id' => 'number',
            //'name|收货人' => 'require',
            //'phone|手机号' => 'require|mobile',
            'address|详细地址' => 'require',
        ]);
        $user = $this->user;
        UserDelivery::updateAddress($user,$req);

        return out();
    }

    public function delDelivery()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $user = $this->user;

        UserDelivery::where('id', $req['id'])->where('user_id', $user['id'])->delete();

        return out();
    }
}

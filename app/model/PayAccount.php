<?php

namespace app\model;

use think\Model;

class PayAccount extends Model
{
    public function getPayTypeTextAttr($value, $data)
    {
        $map = config('map.pay_account')['pay_type_map'];
        return $map[$data['pay_type']];
    }

    public function getRealnameAttr($value, $data)
    {
        return User::where('id', $data['user_id'])->value('realname');
    }
}

<?php

namespace app\model;

use think\Model;

class UserDelivery extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function updateAddress($user,$req)
    {
        $delivery = UserDelivery::where('user_id', $user['id'])->find();
        if ($delivery) {
            UserDelivery::where('user_id', $user['id'])->update(['address'=>$req['address']]);
        }
        else {
            $req['user_id'] = $user['id'];
            $req['phone'] = $user['phone'];
            $req['name'] = $user['realname'];
            UserDelivery::create($req);
        }  
    }
}

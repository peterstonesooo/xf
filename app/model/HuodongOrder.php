<?php

namespace app\model;

use think\Model;

class HuodongOrder extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userDelivery()
    {
        return $this->belongsTo(UserDelivery::class, 'user_id', 'user_id');
    }

}
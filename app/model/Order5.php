<?php

namespace app\model;

use think\Model;

class Order5 extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

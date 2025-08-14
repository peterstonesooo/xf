<?php

namespace app\model;

use think\Model;

class UserSignin extends Model
{

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

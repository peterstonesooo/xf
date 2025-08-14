<?php

namespace app\model;

use think\Model;

class UserPrivateBank extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}

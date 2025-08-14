<?php

namespace app\model;

use think\Model;

class YuanOrder extends Model
{

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
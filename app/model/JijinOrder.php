<?php

namespace app\model;

use think\Model;

class JijinOrder extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
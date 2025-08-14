<?php

namespace app\model;

use think\Model;

class HongliOrder extends Model
{
    public function yuanmeng()
    {
        return $this->hasOne(YuanmengUser::class, 'user_id', 'user_id');
    }

    public function hongli()
    {
        return $this->belongsTo(Hongli::class, 'hongli_id');
    }
}
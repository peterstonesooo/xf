<?php

namespace app\model;

use think\Model;

class UserProduct extends Model
{

    const IS_USING = 0;
    const IS_USED = 1;
    const IS_USE_CANCEL = 2;

    const IS_USE = [
        self::IS_USING => '发放中',
        self::IS_USED => '发放结束',
        //self::IS_USE_CANCEL => '已取消',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->field('realname,phone');
    }
}

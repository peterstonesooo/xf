<?php

namespace app\model;

use think\Model;

class CertificateTrans extends Model
{
    // User 一对一
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

<?php

namespace app\model;

use think\Model;

class UserBalanceLog extends Model
{
    // public function getTypeTextAttr($value, $data)
    // {
    //     $map = config('map.user_balance_log')['type_map'];
    //     return isset($map[$data['type']])?$map[$data['type']]:0;
    // }

    public function getLogTypeTextAttr($value, $data)
    {
        $map = config('map.user_balance_log')['log_type_map'];
        return isset($map[$data['log_type']])?$map[$data['log_type']]:0;
    }

    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.user_balance_log')['status_map'];
        return isset($map[$data['status']])?$map[$data['status']]:0;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function adminUser()
    {
        return $this->belongsTo(AdminUser::class);
    }
}

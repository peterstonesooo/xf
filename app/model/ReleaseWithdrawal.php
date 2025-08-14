<?php

namespace app\model;

use think\Model;

class ReleaseWithdrawal extends Model
{

    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.release_withdrawal_status_map');
        return $map[$data['status']] ?? '';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
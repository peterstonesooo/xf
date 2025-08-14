<?php

namespace app\model;

use think\Model;

class SystemInfo extends Model
{
    public function getTypeTextAttr($value, $data)
    {
        $map = config('map.system_info')['type_map'];
        return $map[$data['type']];
    }

    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.system_info')['status_map'];
        return $map[$data['status']];
    }
}

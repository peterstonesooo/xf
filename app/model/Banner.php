<?php

namespace app\model;

use think\Model;

class Banner extends Model
{
    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.banner')['status_map'];
        return $map[$data['status']];
    }
}

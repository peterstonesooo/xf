<?php

namespace app\model;

use think\Model;

class LevelConfig extends Model
{
    public function getLevelTextAttr($value, $data)
    {
        $map = config('map.level_config')['level_map'];
        return $map[$data['level']];
    }
}

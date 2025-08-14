<?php

namespace app\model;

use think\Model;

class product extends Model
{
    const TYPE_CYCLE = 0;
    const TYPE_DAY = 1;
    const TYPE_DAY_7 = 2;
    const TYPE_NO = 3;

    const TYPE = [
        self::TYPE_CYCLE => '周期分润',
        self::TYPE_DAY => '每日分润',
        self::TYPE_DAY_7 => '每7日分润',
        self::TYPE_NO => '不分润'
    ];

    const STATUS_ON = 1;
    const STATUS_OFF = 0;
    const STATUS = [
        self::STATUS_ON => '启用',
        self::STATUS_OFF => '禁用'
    ];

    const PRODUCT_GROUP = [
        11 => '补助基金',
        1 => '先进补助',
        //2 => '生育补助',另一个表逻辑已经废弃
        3 => '养育补助',
        4 => '教育补助',
        5 => '住房保障补助',
        6 => '部门审计',
        7 => '家庭计划补助（原建党纪念补助）',
        8 => '生育补助',
        9 => '全面生育保障',
        10 => '资兴扶助',
        //100 => '社保类产品',
    ];

    //不是产品的东西
    const GROUP_NO_PRODUCT = [22, 23, 64, 65];
}

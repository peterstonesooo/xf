<?php

namespace app\model;

use think\Model;

class Coin extends Model
{
    public const buyLimit = [
        1 => 1,
        2 => 5,
        3 => 20,
        4 => 100,
        5 => 500,
        6 => 1000,
        7 => 5000,
        8 => 10000,
    ];

    //当前价
    public static function nowPrice($code)
    {
        $codeInfo = Coin::where('code', $code)->find();
        $todayKline = KlineChartNew::where('code_id', $codeInfo['id'])->where('date', date('Y-m-d'))->find();
        if ($todayKline) {
            if (time() < strtotime(date('Y-m-d')) + 9.5 * 3600) {
                $lastDayKline = KlineChartNew::where('code_id', $codeInfo['id'])->where('id', '<', $todayKline['id'])->order('id', 'desc')->find();
                return $lastDayKline['price25'];
            } elseif (time() >= strtotime(date('Y-m-d')) + 15 * 3600) {
                return $todayKline['price25'];
            } else {
                $index = floor((time() - (strtotime(date('Y-m-d')) + 9.5 * 3600)) % 600);
                return $todayKline['price' . $index];
            }
        } else {
            $lastDayKline = KlineChartNew::where('code_id', $codeInfo['id'])->order('date', 'desc')->find();
            return $lastDayKline['price25'];
        }
    }

}
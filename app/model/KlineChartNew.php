<?php

namespace app\model;

use think\Model;

class KlineChartNew extends Model
{
    public static function getTodayPrice(){
        $today = date("Y-m-d");
        $todayPrice=KlineChartNew::Where('date',$today)->value('price1');
        if(!$todayPrice){
            $todayPrice = KlineChartNew::order('date','desc')->value('price1');
        }
        return $todayPrice;
    }
}

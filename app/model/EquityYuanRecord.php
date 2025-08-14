<?php

namespace app\model;

use think\Model;

class EquityYuanRecord extends Model
{
    public function getExchangeDateAttr($value, $data)
    {
        if (!empty($data['exchange_time'])) {
            return date('Y-m-d H:i:s', $data['exchange_time']);
        }
        return '';
    }

    public function getExchangePriceAttr($value)
    {
        if ($value == 0) {
            $chart = KlineChart::where('date', date('Y-m-d'))->find();
            if (empty($chart)) {
                return 0;
            }
            $chart_data = json_decode($chart['chart_data'], true);
            $chart_data = array_column($chart_data, 'value', 'time');
            $time = (int)date('Hi');
            if (($time >= 930 && $time <= 1130) || ($time >= 1300 && $time <= 1500)) {
                $minute = date('i');
                $hour = date('H');
                $arr = str_split($minute);
                $start = $hour. ':' .$arr[0]. '0';
                if ($arr[0] == 5) {
                    $str1 = $hour + 1;
                    $str1 = sprintf("%02d", $str1);
                    $end = $str1. ':00';
                }
                else {
                    $str1 = $arr[0] + 1;
                    $end = $hour. ':' .$str1 . '0';
                }

                $diff = $chart_data[$start] - $chart_data[$end];
                return round($chart_data[$start] + $diff/10*($arr[1]), 4);
            }
            else {
                if ($time < 930) {
                    $ychart = KlineChart::where('date', date('Y-m-d', strtotime('-1 day')))->find();
                    if (empty($ychart)) {
                        return 0;
                    }
                    $ychart_data = json_decode($ychart['chart_data'], true);
                    $ychart_data = array_column($ychart_data, 'value', 'time');
                    return $ychart_data['15:00'];
                }
                elseif ($time > 1130 && $time < 1300) {
                    return $chart_data["11:30"];
                }
                else {
                    return $chart_data["15:00"];
                }
            }
        }

        return $value;
    }
}

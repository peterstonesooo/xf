<?php

namespace app\api\controller;

use app\model\KlineChart;

class ChartController extends AuthController
{
    public function klineChartList()
    {
        $req = $this->validate(request(), [
            'date' => 'date',
        ]);

        $builder = KlineChart::order('date', 'asc');
        if (!empty($req['date'])) {
            $builder->where('date', $req['date']);
        }
        $data = $builder->select();

        return out($data);
    }

    public function klineChartDaysData()
    {
        $data = KlineChart::order('date', 'asc')->select()->toArray();
        $dates = [];
        $charts = [];
        foreach ($data as $v) {
            $dates[] = $v['date'];
            $charts[] = [$v['open_price'], $v['close_price'], $v['min_price'], $v['max_price']];
        }
        return out(['dates' => $dates, 'charts' => $charts]);
    }
}

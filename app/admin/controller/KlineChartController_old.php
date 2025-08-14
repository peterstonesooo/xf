<?php

namespace app\admin\controller;

use app\model\KlineChart;

class KlineChartController extends CommonController
{
    public function klineChartPage()
    {
        return $this->fetch();
    }

    public function klineChartList()
    {
        $req = request()->post();

        $builder = KlineChart::order('date', 'desc');
        if (isset($req['kline_chart_id']) && $req['kline_chart_id'] !== '') {
            $builder->where('id', $req['kline_chart_id']);
        }
        if (isset($req['date']) && $req['date'] !== '') {
            $builder->where('date', $req['date']);
        }

        $data = $builder->paginate();

        return out($data);
    }

    public function klineChartDaysData()
    {
        $data = KlineChart::order('date', 'desc')->select()->toArray();
        $dates = [];
        $charts = [];
        foreach ($data as $v) {
            $dates[] = $v['date'];
            $charts[] = [$v['open_price'], $v['close_price'], $v['min_price'], $v['max_price']];
        }
        return out(['dates' => $dates, 'charts' => $charts]);
    }

    public function saveKlineChart()
    {
        $req = $this->validate(request(), [
            'date|日期' => 'require|max:20',
            'max_price|最高价' => 'require|float',
            'min_price|最低价' => 'require|float',
            'float_ratio|浮动比例' => 'require|float',
            'open_price|开盘价' => 'require|float',
            'close_price|收盘价' => 'require|float',
            'chart_data|图表数据' => 'require',
        ]);

        if (KlineChart::where('date', $req['date'])->count()) {
            KlineChart::where('date', $req['date'])->update($req);
        }
        else {
            KlineChart::create($req);
        }

        return out();
    }
}

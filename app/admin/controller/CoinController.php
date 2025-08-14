<?php

namespace app\admin\controller;

use app\model\Coin;
use app\model\CoinOrder;
use app\model\KlineChartNew;
use app\model\UserCoinTransferLog;

class CoinController extends AuthController
{
    //持币列表
    public function coinHold()
    {
        $req = request()->param();
        $builder = CoinOrder::alias('o');
        if (isset($req['code_id']) && $req['code_id'] !== '') {
            $builder->where('o.code_id', $req['code_id']);
        }

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('o.user_id', $req['user_id']);
        }

        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', $req['phone']);
        }
        $builder = $builder->field('o.*, u.phone, c.code')->leftJoin('mp_user u', 'o.user_id = u.id')->leftJoin('mp_coin c', 'c.id = o.code_id')->order('id', 'desc');
        $list = $builder->paginate(['query' => $req]);
        $this->assign('data', $list);
        $this->assign('coin', Coin::select());
        $this->assign('req', $req);
        return $this->fetch();
    }

    //币种列表
    public function list()
    {
        $req = request()->param();
        $builder = Coin::alias('c')->order('id', 'desc');
        $list = $builder->paginate(['query' => $req]);
        $this->assign('data', $list);
        return $this->fetch();
    }

    public function add()
    {
        return $this->fetch();
    }

    //添加币种
    public function addSubmit()
    {
        $req = $this->validate(request(), [
            'code|币种代码' => 'require',
            'description|项目简介' => 'require',
            'layer1|一级返佣比例' => 'require',
            'layer2|二级返佣比例' => 'require',
            'id' => 'number',
        ]);
        if ($img = upload_file('icon', false,false)) {
            $req['icon'] = $img;
        }
        
        if (isset($req['id']) && $req['id']) {
            Coin::where('id', $req['id'])->data(['code' => $req['code'], 'description' => $req['description'], 'icon' => $req['icon'], 'layer1' => $req['layer1'], 'layer2' => $req['layer2']])->update();
        } else {
            $req['created_at'] = date('Y-m-d H:i:s');
            Coin::insert($req);
        }
        

        return out();
    }

    public function changeCoin()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        Coin::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    public function edit()
    {
        $req = request()->get();
        $data = Coin::where('id', $req['id'])->find();
        $this->assign('data', $data);
        return $this->fetch();
    }


    public function transferLog()
    {
        $req = request()->param();
        $builder = UserCoinTransferLog::alias('l');
        if (isset($req['code_id']) && $req['code_id'] !== '') {
            $builder->where('l.code_id', $req['code_id']);
        }

        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', $req['phone']);
        }
        if (isset($req['phone1']) && $req['phone1'] !== '') {
            $builder->where('u1.phone', $req['phone1']);
        }
        $builder = $builder->field('l.*, u.phone, c.code, u1.phone as phone1')->leftJoin('mp_user u', 'l.user_id = u.id')->leftJoin('mp_coin c', 'c.id = l.code_id')->leftJoin('mp_user u1', 'l.to_user_id = u1.id')->order('l.id', 'desc');
        $list = $builder->paginate(['query' => $req]);
        $this->assign('data', $list);
        $this->assign('coin', Coin::select());
        $this->assign('req', $req);
        return $this->fetch();
    }

    public function klineChartPage()
    {
        return $this->fetch();
    }

    public function klineChart()
    {
        $data = KlineChartNew::alias('k')->field('k.*, c.code')->leftJoin('coin c', 'c.id = k.code_id')->order('k.date', 'desc')->select();
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function showKline(){
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = KlineChartNew::where('id', $req['id'])->find();
        }
        $coin = Coin::select();
        $this->assign('data', $data);
        $this->assign('coin', $coin);

        return $this->fetch();
    }

    public function testUp(){
        return $this->fetch();
    }

    public function addKline(){
        $req = $this->validate(request(), [
            'date|日期' => 'date',
            'price1|价格1' => 'float',
            'price2|价格2' => 'float',
            'price3|价格3' => 'float',
            'price4|价格4' => 'float',
            'price5|价格5' => 'float',
            'price6|价格6' => 'float',
            'price7|价格7' => 'float',
            'price8|价格8' => 'float',
            'price9|价格9' => 'float',
            'price10|价格10' => 'float',
            'price11|价格11' => 'float',
            'price12|价格12' => 'float',
            'price13|价格13' => 'float',
            'price14|价格14' => 'float',
            'price15|价格15' => 'float',
            'price16|价格16' => 'float',
            'price17|价格17' => 'float',
            'price18|价格18' => 'float',
            'price19|价格19' => 'float',
            'price20|价格20' => 'float',
            'price21|价格21' => 'float',
            'price22|价格22' => 'float',
            'price23|价格23' => 'float',
            'price24|价格24' => 'float',
            'price25|价格25' => 'float',
            'code_id|币种' => 'number',
        ]);
        $re = KlineChartNew::where('date',$req['date'])->find();
        if($re == null){
            KlineChartNew::create($req);
            return out();
        }else{
            return out(null,10001,'填写的日期已经存在');
        }
        
    }

    public function editKline(){
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'date|日期' => 'date',
            'price1|价格1' => 'float',
            'price2|价格2' => 'float',
            'price3|价格3' => 'float',
            'price4|价格4' => 'float',
            'price5|价格5' => 'float',
            'price6|价格6' => 'float',
            'price7|价格7' => 'float',
            'price8|价格8' => 'float',
            'price9|价格9' => 'float',
            'price10|价格10' => 'float',
            'price11|价格11' => 'float',
            'price12|价格12' => 'float',
            'price13|价格13' => 'float',
            'price14|价格14' => 'float',
            'price15|价格15' => 'float',
            'price16|价格16' => 'float',
            'price17|价格17' => 'float',
            'price18|价格18' => 'float',
            'price19|价格19' => 'float',
            'price20|价格20' => 'float',
            'price21|价格21' => 'float',
            'price22|价格22' => 'float',
            'price23|价格23' => 'float',
            'price24|价格24' => 'float',
            'price25|价格25' => 'float',
            'code_id|币种' => 'number',
        ]);
        KlineChartNew::where('id', $req['id'])->update($req);
        return out();    
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

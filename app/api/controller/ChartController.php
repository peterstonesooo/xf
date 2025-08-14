<?php

namespace app\api\controller;

use app\model\KlineChart;
use app\model\KlineChartNew;
use app\model\Coin;

class ChartController extends AuthController
{
    // public function klineChartList()
    // {
    //     $req = $this->validate(request(), [
    //         'date' => 'date',
    //     ]);

    //     $builder = KlineChart::order('date', 'asc');
    //     if (!empty($req['date'])) {
    //         $builder->where('date', $req['date']);
    //     }
    //     $data = $builder->select();

    //     return out($data);
    // }

    public function coinList()
    {
        return out(Coin::select());
    }

    public function klineChart(){
        $user = $this->user;
        $req = $this->validate(request(), [
            'code' => 'require',
        ]);
        $coin = Coin::where('code', $req['code'])->find();
        $k = KlineChartNew::field('date,price1,price2,price3,price4,price5,price6,price7,price8,price9,price10,price11,price12,price13,price14,price15,price16,price17,price18,price19,price20,price21,price22,price23,price24,price25')->where('code_id', $coin['id'])->select()->toArray();
        $data = [];
        $time=[];
        $price=[];
        if(!empty($k)){
            foreach($k as $v){
                $a['price1'] = $v['price1'];
                $a['price2'] = $v['price2'];
                $a['price3'] = $v['price3'];
                $a['price4'] = $v['price4'];
                $a['price5'] = $v['price5'];
                $a['price6'] = $v['price6'];
                $a['price7'] = $v['price7'];
                $a['price8'] = $v['price8'];
                $a['price9'] = $v['price9'];
                $a['price10'] = $v['price10'];
                $a['price11'] = $v['price11'];
                $a['price12'] = $v['price12'];
                $a['price13'] = $v['price13'];
                $a['price14'] = $v['price14'];
                $a['price15'] = $v['price15'];
                $a['price16'] = $v['price16'];
                $a['price17'] = $v['price17'];
                $a['price18'] = $v['price18'];
                $a['price19'] = $v['price19'];
                $a['price20'] = $v['price20'];
                $a['price21'] = $v['price21'];
                $a['price22'] = $v['price22'];
                $a['price23'] = $v['price23'];
                $a['price24'] = $v['price24'];
                $a['price25'] = $v['price25'];
                if(strtotime($v['date']) < time()){
                    //$data[$v['date']][] = $v['price1'];
                    //$data[] = ['time'=>$v['date'],'price'=>$v['price1']];
                    $time[]=$v['date'];
                    
                    $p=[];
                    $p['open'] = $v['price1'];
                    $p['close'] = $v['price25'];
                    $p['min'] = min($a);
                    $p['max'] = max($a);
                    $price[] =$p;

/*                     $data[$v['date']][] = $v['price25'];
                    $data[$v['date']][] = min($a);
                    $data[$v['date']][] = max($a); */
                }
            }
            $data = [
                'time'=>$time,
                'price'=>$price,
            ];
            // unset($data[date('Y-m-d',time())]);
        }
       
        return out($data);
    }
    
    public function klineChartDays(){
        $user = $this->user;
        $k = KlineChartNew::where('date',date('Y-m-d',time()))->field('price1,price2,price3,price4,price5,price6,price7,price8,price9,price10,price11,price12,price13,price14,price15,price16,price17,price18,price19,price20,price21,price22,price23,price24,price25')->find();
        if(!empty($k)){
            $data['09:30'] = $k['price1'];
            $data['09:40'] = $k['price2'];
            $data['09:50'] = $k['price3'];
            $data['10:00'] = $k['price4'];
            $data['10:10'] = $k['price5'];
            $data['10:20'] = $k['price6'];
            $data['10:30'] = $k['price7'];
            $data['10:40'] = $k['price8'];
            $data['10:50'] = $k['price9'];
            $data['11:00'] = $k['price10'];
            $data['11:10'] = $k['price11'];
            $data['11:20'] = $k['price12'];
            $data['11:30'] = $k['price13'];
            $data['13:10'] = $k['price14'];
            $data['13:20'] = $k['price15'];
            $data['13:30'] = $k['price16'];
            $data['13:40'] = $k['price17'];
            $data['13:50'] = $k['price18'];
            $data['14:00'] = $k['price19'];
            $data['14:10'] = $k['price20'];
            $data['14:20'] = $k['price21'];
            $data['14:30'] = $k['price22'];
            $data['14:40'] = $k['price23'];
            $data['14:50'] = $k['price24'];
            $data['15:00'] = $k['price25'];
        }else{
            $data = [];
        }
        
        return out($data);
    }

    // public function klineChartDaysData()
    // {
    //     $data = KlineChart::order('date', 'asc')->select()->toArray();
    //     $dates = [];
    //     $charts = [];
    //     foreach ($data as $v) {
    //         $dates[] = $v['date'];
    //         $charts[] = [$v['open_price'], $v['close_price'], $v['min_price'], $v['max_price']];
    //     }
    //     return out(['dates' => $dates, 'charts' => $charts]);
    // }
}

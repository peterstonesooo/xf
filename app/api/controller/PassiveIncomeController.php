<?php

namespace app\api\controller;

use app\model\PassiveIncomeRecord;
use app\model\User;
use app\model\ProcessReview;
use app\model\EquityYuanRecord;
use app\model\Order;
use Exception;
use think\facade\Db;

class PassiveIncomeController extends AuthController
{

    public function passiveIncomeList()
    {
        $user = $this->user;
        $sum = PassiveIncomeRecord::where('user_id',$user['id'])->where('status','>',1)->sum('amount');
        $list = PassiveIncomeRecord::with('orders')->where('user_id', $user['id'])->where('status', '>', 1)->order('id', 'desc')->order('is_finish','asc')->paginate(5,false,['query'=>request()->param()])->each(function($item, $key){
            $item['order_sn'] = $item['orders']['order_sn'];
            return $item;
        });
        
        $data['list'] = $list;
        $data['passive_income_sum']=$sum;
        return out($data);
    }

    public function passiveIncomeList2()
    {
        $user = $this->user;
        $data = PassiveIncomeRecord::with('orders')->where('user_id', $user['id'])->where('status', '>', 1)->order('id', 'desc')->order('is_finish','asc')->paginate(5,false,['query'=>request()->param()])->each(function($item, $key){
            $uid = $item["user_id"];
            $item['is_active'] = User::where('id', $uid)->value('is_active');
            $item['is_finish'] = PassiveIncomeRecord::where('id',$item['id'])->value('is_finish');
            return $item;
        });
        return out($data);
    }

    public function ProcessList(){
        $req = $this->validate(request(),[
            'type' => 'require|in:1,2,3,4',
            'id' => 'require|number',
        ]);
        $user = $this->user;
        $data = [];
        $days = 0;
        if($req['type'] == 1){
            $cre = PassiveIncomeRecord::where('id',$req['id'])->field(['created_at','updated_at','amount'])->find();
            // $days = intval((time()-strtotime($cre['created_at'])) / 60 / 60 / 24);//过去几天
            // $days -= dbconfig('passive_income_review');
            $days = intval((time()-strtotime($cre['updated_at'])) / 60 / 60 / 24);
            $data['amount'] = $cre['amount'];
        }
        if($req['type'] == 2){
            $cre = EquityYuanRecord::where('id',$req['id'])->field(['created_at','num'])->find();
            $days = intval((time()-strtotime($cre['created_at'])) / 60 / 60 / 24);
            $days -= dbconfig('digital_yuan_switch');
            $data['amount'] = $cre['num'];
        }
        if($req['type'] == 3){
            $cre = Order::where('id',$req['id'])->field(['created_at','single_amount'])->find();
            $days = intval((time()-strtotime($cre['created_at'])) / 60 / 60 / 24);
            $days -= 77;
            $data['amount'] = $cre['single_amount'];
        }
        if($req['type'] == 4){
            $cre = EquityYuanRecord::where('id',$req['id'])->field(['exchange_price','exchange_time','num'])->find();
            $days = intval((time()-$cre['exchange_time']) / 60 / 60 / 24);
            $data['amount'] = $cre['exchange_price'] * $cre['num'];
        }
        $pro = ProcessReview::where('type',$req['type'])->order('sort','asc')->select()->toArray();
        if(!empty($pro)){
            $i = 0;
            foreach($pro as $v){
                $days -= $v['number'];
                if($days >= 0){
                    $data['data'][$i]['name'] = $v['name'];
                    $data['data'][$i]['msg'] = '审核成功';
                }else{
                    $data['data'][$i]['name'] = $v['name'];
                    $data['data'][$i]['msg'] = '正在审核';
                    break;
                }
                $i++;
            }
        }

        return out($data);
    }

    public function receivePassiveIncome()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $user = $this->user;

        Db::startTrans();
        try {
            $record = PassiveIncomeRecord::where('id', $req['id'])->where('user_id', $user['id'])->where('status', 2)->lock(true)->find();
            if (empty($record)) {
                exit_out(null, 10001, '该订单无被动收益可领取');
            }
            $isActive = User::where('id', $user['id'])->value('is_active');
            if($isActive == 0){
                exit_out(null, 10001, '需要激活才可以领取被动收益');
            }
            if($record['days'] >= dbconfig('passive_income_review')){
                PassiveIncomeRecord::where('id', $record['id'])->update(['status' => 3,'is_finish' => 1]);
                PassiveIncomeRecord::create([
                    'user_id' => $user['id'],
                    'order_id' => $record['order_id'],
                    'execute_day' => date('Ymd'),
                ]);
                Db::commit();
                return out(['type' => 1,'id' => $record['id']]);
            }

            PassiveIncomeRecord::where('id', $record['id'])->update(['status' => 3]);

            User::changeBalance($user['id'], $record['amount'], 14, $record['id']);

            PassiveIncomeRecord::create([
                'user_id' => $user['id'],
                'order_id' => $record['order_id'],
                'execute_day' => date('Ymd'),
            ]);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
}

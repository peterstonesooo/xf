<?php

namespace app\admin\controller;

use app\model\JijinOrder;
use app\model\Order;
use app\model\OrderLog;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\User;
use app\model\YuanOrder;
use Exception;
use think\facade\Db;
use think\facade\Session;

class YuanOrderController extends AuthController
{
    public function list()
    {
        $req = request()->param();
        $builder =  YuanOrder::order('id', 'desc');
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('user_id', $user_ids);
        }
        // if (isset($req['status']) && $req['status'] !== '') {
        //     $builder->where('status', $req['status']);
        // }
        if (!empty($req['start_date'])) {
            $time = $req['start_date'] . ' 00:00:00';
            $builder->where('created_at', '>=', $time);
        }
        if (!empty($req['end_date'])) {
            $time = $req['end_date'] . ' 23:59:59';
            $builder->where('created_at', '<=', $time);
        }
        $data = $builder->paginate(['query' => $req]);
        $builder1 = clone $builder;
        $total_amount = round($builder1->sum('amount'), 2);
        $builder2 = clone $builder;
        $total_shenbao_amount = round($builder2->sum('shenbao_amount'), 2);
        $this->assign('total_shenbao_amount', $total_shenbao_amount);
        $this->assign('total_amount', $total_amount);
        $this->assign('count', $builder->count());
        $this->assign('data',$data);
        return $this->fetch();  
    }
}

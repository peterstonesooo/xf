<?php

namespace app\admin\controller;

use app\model\Order4;
use app\model\OrderLog;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\User;
use Exception;
use think\facade\Db;
use think\facade\Session;

class Order4Controller extends AuthController
{
    public function orderList()
    {
        $req = request()->param();

        if (!empty($req['channel'])||!empty($req['mark'])) {
            $builder = Order4::alias('o')->leftJoin('payment p', 'p.order_id = o.id')->field('o.*')->order('o.id', 'desc');
        }else{
            $builder = Order4::alias('o')->field('o.*')->order('o.id', 'desc');
        }
        if (isset($req['order_id']) && $req['order_id'] !== '') {
            $builder->where('o.id', $req['order_id']);
        }
        if (isset($req['up_user_id']) && $req['up_user_id'] !== '') {
            $builder->where('o.up_user_id', $req['up_user_id']);
        }
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('o.user_id', $user_ids);
        }
        if (isset($req['order_sn']) && $req['order_sn'] !== '') {
            $builder->where('o.order_sn', $req['order_sn']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('o.status', $req['status']);
        }
        if (isset($req['project_id']) && $req['project_id'] !== '') {
            $builder->where('o.project_id', $req['project_id']);
        }
        if (isset($req['project_name']) && $req['project_name'] !== '') {
            $builder->whereLike('o.project_name', '%'.$req['project_name'].'%');
        }
        if (isset($req['pay_method']) && $req['pay_method'] !== '') {
            $builder->where('o.pay_method', $req['pay_method']);
        }
        if (isset($req['pay_time']) && $req['pay_time'] !== '') {
            $builder->where('o.pay_time', $req['pay_time']);
        }
        if (!empty($req['channel'])) {
            $builder->where('p.channel', $req['channel']);
        }
        if (!empty($req['mark'])) {
            $builder->where('p.mark', $req['mark']);
        }
        if (!empty($req['start_date'])) {
            $time = $req['start_date'] . ' 00:00:00';
            $builder->where('o.created_at', '>=', $time);
        }
        if (!empty($req['end_date'])) {
            $time = $req['end_date'] . ' 23:59:59';
            $builder->where('o.created_at', '<=', $time);
        }
        if (isset($req['project_group_id']) && $req['project_group_id'] !== '') {
            $builder->where('o.project_group_id', $req['project_group_id']);
        }
        if (isset($req['project_jijin_name']) && $req['project_jijin_name'] !== '') {
            $builder->where('o.project_group_id', 2);
            $builder->where('o.project_name', $req['project_jijin_name']);
        }
        if (!empty($req['export'])) {
            $list = $builder->select();
            foreach ($list as $v) {
                $v->account_type = $v['user']['phone'] ?? '';
                $v->realname=$v['user']['realname'] ?? '';
                $v->address=$v['yuanmeng']['user_address'] ?? '';
            }
            create_excel($list, [
                'id' => '序号',
                'account_type' => '用户',
                'order_sn' => '单号',
                'project_name' => '项目名称',
                'created_at' => '创建时间'
            ], '订单记录-' . date('YmdHis'));
        }


        if (isset($req['project_group_id']) && $req['project_group_id'] !== '') {
            if($req['project_group_id'] == 5) {
                $list = $builder->select();
                $total_buy_amount = 0;
                foreach ($list as $v) {
                    $total_buy_amount += $v->buy_amount;
                }
                $this->assign('total_buy_amount', $total_buy_amount);
            } else {
                $builder1 = clone $builder;
                $total_buy_amount = round($builder1->sum('o.price'), 2);
                $this->assign('total_buy_amount', $total_buy_amount);
            }
        } else {
            $builder1 = clone $builder;
            $total_buy_amount = round($builder1->sum('o.price'), 2);
            $this->assign('total_buy_amount', $total_buy_amount);
        }

        $builder2 = clone $builder;
        $total_buy_integral = 0;
        $this->assign('total_buy_integral', $total_buy_integral);

        $builder3 = clone $builder;
        $total_gift_equity = 0;
        $this->assign('total_gift_equity', $total_gift_equity);

        $builder4 = clone $builder;
        $total_gift_digital_yuan = 0;
        $this->assign('total_gift_digital_yuan', $total_gift_digital_yuan);

        $data = $builder->paginate(['query' => $req]);
        //var_dump($data);
        $this->assign('req', $req);
        $this->assign('data', $data);
        $groups = config('map.project.group');
        $this->assign('groups',$groups);
        $process_map = config('map.order.process_map');
        $this->assign('process_map',$process_map);

        $groups_jijin = Project::where('project_group_id', 2)->select()->toArray();
        $this->assign('groups_jijin',$groups_jijin);
        $this->assign('count', $builder->count());
        return $this->fetch();
    }

    public function orderListFree()
    {
        $req = request()->param();

        if (!empty($req['channel'])||!empty($req['mark'])) {
            $builder = Order4::alias('o')->leftJoin('payment p', 'p.order_id = o.id')->field('o.*')->order('o.id', 'desc');
        }else{
            $builder = Order4::alias('o')->field('o.*')->order('o.id', 'desc');
        }

        $builder->where('o.is_gift', 1);

        if (isset($req['order_id']) && $req['order_id'] !== '') {
            $builder->where('o.id', $req['order_id']);
        }
        if (isset($req['up_user_id']) && $req['up_user_id'] !== '') {
            $builder->where('o.up_user_id', $req['up_user_id']);
        }
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('o.user_id', $user_ids);
        }
        if (isset($req['order_sn']) && $req['order_sn'] !== '') {
            $builder->where('o.order_sn', $req['order_sn']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('o.status', $req['status']);
        }
        if (isset($req['project_id']) && $req['project_id'] !== '') {
            $builder->where('o.project_id', $req['project_id']);
        }
        if (isset($req['project_name']) && $req['project_name'] !== '') {
            $builder->whereLike('o.project_name', '%'.$req['project_name'].'%');
        }
        if (isset($req['pay_method']) && $req['pay_method'] !== '') {
            $builder->where('o.pay_method', $req['pay_method']);
        }
        if (isset($req['pay_time']) && $req['pay_time'] !== '') {
            $builder->where('o.pay_time', $req['pay_time']);
        }
        if (!empty($req['channel'])) {
            $builder->where('p.channel', $req['channel']);
        }
        if (!empty($req['mark'])) {
            $builder->where('p.mark', $req['mark']);
        }
        if (!empty($req['start_date'])) {
            $time = $req['start_date'] . ' 00:00:00';
            $builder->where('o.created_at', '>=', $time);
        }
        if (!empty($req['end_date'])) {
            $time = $req['end_date'] . ' 23:59:59';
            $builder->where('o.created_at', '<=', $time);
        }
        if (isset($req['project_group_id']) && $req['project_group_id'] !== '') {
            $builder->where('o.project_group_id', $req['project_group_id']);
        }
        if (isset($req['project_jijin_name']) && $req['project_jijin_name'] !== '') {
            $builder->where('o.project_group_id', 2);
            $builder->where('o.project_name', $req['project_jijin_name']);
        }
        if (!empty($req['export'])) {
            $list = $builder->select();
            foreach ($list as $v) {
                $v->account_type = $v['user']['phone'] ?? '';
                $v->realname=$v['user']['realname'] ?? '';
                $v->address=$v['yuanmeng']['user_address'] ?? '';
            }
            create_excel($list, [
                'id' => '序号',
                'account_type' => '用户',
                'order_sn' => '单号',
                'project_name' => '项目名称',
                'created_at' => '创建时间'
            ], '订单记录-' . date('YmdHis'));
        }


        if (isset($req['project_group_id']) && $req['project_group_id'] !== '') {
            if($req['project_group_id'] == 5) {
                $list = $builder->select();
                $total_buy_amount = 0;
                foreach ($list as $v) {
                    $total_buy_amount += $v->buy_amount;
                }
                $this->assign('total_buy_amount', $total_buy_amount);
            } else {
                $builder1 = clone $builder;
                $total_buy_amount = round($builder1->sum('o.price'), 2);
                $this->assign('total_buy_amount', $total_buy_amount);
            }
        } else {
            $builder1 = clone $builder;
            $total_buy_amount = round($builder1->sum('o.price'), 2);
            $this->assign('total_buy_amount', $total_buy_amount);
        }

        $builder2 = clone $builder;
        $total_buy_integral = 0;
        $this->assign('total_buy_integral', $total_buy_integral);

        $builder3 = clone $builder;
        $total_gift_equity = 0;
        $this->assign('total_gift_equity', $total_gift_equity);

        $builder4 = clone $builder;
        $total_gift_digital_yuan = 0;
        $this->assign('total_gift_digital_yuan', $total_gift_digital_yuan);

        $data = $builder->paginate(['query' => $req]);
        //var_dump($data);
        $this->assign('req', $req);
        $this->assign('data', $data);
        $groups = config('map.project.group');
        $this->assign('groups',$groups);
        $process_map = config('map.order.process_map');
        $this->assign('process_map',$process_map);

        $groups_jijin = Project::where('project_group_id', 2)->select()->toArray();
        $this->assign('groups_jijin',$groups_jijin);
        $this->assign('count', $builder->count());
        return $this->fetch();
    }

    public function auditOrder()
    {
        $req = request()->post();
        $this->validate($req, [
            'id' => 'require|number',
            'status' => 'require|in:2',
        ]);

        $order = Order4::where('id', $req['id'])->find();
        if ($order['status'] != 1) {
            return out(null, 10001, '该记录状态异常');
        }
        if (!in_array($order['pay_method'], [2,3,4,6])) {
            return out(null, 10002, '审核记录异常');
        }

        Db::startTrans();
        try {
            Payment::where('order_id', $order['id'])->update(['payment_time' => time(), 'status' => 2]);

            Order4::where('id', $order['id'])->update(['is_admin_confirm' => 1]);
            Order4::orderPayComplete($order['id']);
            // 判断通道是否超过最大限额，超过了就关闭通道
            $payment = Payment::where('order_id', $order['id'])->find();
            $userModel = new User();
            $userModel->teamBonus($order['user_id'],$payment['pay_amount'],$payment['id']);

            PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function addTime(){
        

            if(request()->isPost()){
                $req = request()->post();
                $this->validate($req, [
                    'project_id' => 'require|number',
                    'day_num' => 'require|number',
                ]);
                $updateData=[
                    'end_time'=>Db::raw('end_time+'.$req['day_num']*24*3600),
                    'period'=>Db::raw('period+'.$req['day_num']),
                    'period_change_day'=>$req['day_num'],
                ];

                $num = Order4::where('project_id',$req['project_id'])->where('status',2)->update($updateData);

                return out(['msg'=>$num."个订单已增加".$req['day_num']."天"]);
            }else{
                $projectList = \app\model\Project::field('id,name')->where('status',1)->select();
                $this->assign('projectList', $projectList);
                return $this->fetch();
            }
    }
}

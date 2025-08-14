<?php

namespace app\admin\controller;

use app\model\AdminUser;
use app\model\AuthGroupAccess;
use app\model\OrderDingtou;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\User;
use Exception;
use think\facade\Db;
use think\facade\Session;

class OrderDingtouController extends AuthController
{
    public function orderList()
    {
        $req = request()->param();

        if (!empty($req['channel'])||!empty($req['mark'])) {
            $builder = OrderDingtou::alias('o')->leftJoin('payment p', 'p.order_id = o.id')->leftJoin('user u', 'u.id = o.user_id')->field('o.*,u.phone')->order('o.id', 'desc');
        }else{
            $builder = OrderDingtou::alias('o')->leftJoin('user u', 'u.id = o.user_id')->field('o.*,u.phone')->order('o.id', 'desc');
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
            $adminUser = $this->adminUser = Session::get('admin_user');
            $adminUser = AdminUser::field('id,status')->where('id', $adminUser['id'])->find();
            $count = AuthGroupAccess::where('admin_user_id', $adminUser['id'])->where('auth_group_id', 3)->count();
            $time = strtotime($req['start_date'] . ' 00:00:00');
            $builder->where('o.pay_time', '>=', $time);
            if($count && $req['start_date'] <=  date('Y-m-d',strtotime('-30 day'))) {
                $builder->where('o.pay_time', '>=', strtotime(date('Y-m-d 00:00:00',strtotime('-30 day'))));
            }
        } else {
            $adminUser = $this->adminUser = Session::get('admin_user');
            $adminUser = AdminUser::field('id,status')->where('id', $adminUser['id'])->find();
            $count = AuthGroupAccess::where('admin_user_id', $adminUser['id'])->where('auth_group_id', 3)->count();
            if($count) {
                $builder->where('o.pay_time', '>=', strtotime(date('Y-m-d 00:00:00',strtotime('-30 day'))));
            }
        }

        if (!empty($req['end_date'])) {
            $time = strtotime($req['end_date'] . ' 23:59:59');
            $builder->where('o.pay_time', '<=', $time);
        }

        if (!empty($req['export'])) {
            $list = $builder->select();
            foreach ($list as $v) {
                $v->account_type = $v['user']['phone'] ?? '';
                $v->realname=$v['user']['realname'] ?? '';
            }
            create_excel($list, [
                'id' => '序号',
                'account_type' => '用户',
                'realname'=>'姓名',  
                'order_sn' => '单号',
                'project_name' => '项目名称',
                'created_at' => '创建时间'
            ], '定投订单记录-' . date('YmdHis'));
        }

        $data = $builder->paginate(['query' => $req]);
        $this->assign('req', $req);
        $this->assign('data', $data);
        $groups = config('map.project.group');
        $this->assign('groups',$groups);
        $this->assign('auth_check', $this->adminUser['authGroup'][0]['title']);
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

        $order = OrderDingtou::where('id', $req['id'])->find();
        if ($order['status'] != 1) {
            return out(null, 10001, '该记录状态异常');
        }
        if (!in_array($order['pay_method'], [2,3,4,6])) {
            return out(null, 10002, '审核记录异常');
        }

        Db::startTrans();
        try {
            Payment::where('order_id', $order['id'])->update(['payment_time' => time(), 'status' => 2]);

            OrderDingtou::where('id', $order['id'])->update(['is_admin_confirm' => 1]);
            OrderDingtou::orderPayComplete($order['id']);
            // 判断通道是否超过最大限额，超过了就关闭通道
            $payment = Payment::where('order_id', $order['id'])->find();
            if (!empty($payment)) {
                $config = PaymentConfig::where('id', $payment['payment_config_id'])->find();
                if (!empty($config)) {
                    $total = Payment::where('payment_config_id', $payment['payment_config_id'])->where('status', 2)->sum('amount');
                    if ($total >= $config['max_amount']) {
                        PaymentConfig::where('id', $payment['payment_config_id'])->update(['status' => 0]);
                    }
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }
}
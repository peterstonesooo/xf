<?php

namespace app\admin\controller;

use app\model\AdminUser;
use app\model\AuthGroupAccess;
use app\model\OrderTiyan;
use app\model\OrderLog;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\User;
use Exception;
use think\facade\Db;
use think\facade\Session;

class OrderTiyanController extends AuthController
{
    public function orderList()
    {
        $req = request()->param();

        if (!empty($req['channel'])||!empty($req['mark'])) {
            $builder = OrderTiyan::alias('o')->leftJoin('payment p', 'p.order_id = o.id')->leftJoin('user u', 'u.id = o.user_id')->field('o.*,u.phone,u.realname')->order('o.id', 'desc');
        }else{
            $builder = OrderTiyan::alias('o')->leftJoin('user u', 'u.id = o.user_id')->field('o.*,u.phone,u.realname')->order('o.id', 'desc');
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
            if($req['status'] == 8){
                $builder->where('u.tiyan_wallet_lock', 0);
            }else if($req['status'] == 9){
                $builder->where('u.tiyan_wallet_lock', '>', 0);
            }else{
                $builder->where('o.status', $req['status']);
            }
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
                $v->account_type = $v['phone'] ?? '';
                $v->realname = $v['realname'] ?? '';
            }
            create_excel($list, [
                'id' => '序号',
                'account_type' => '用户',
                'realname'=>'姓名',  
                'order_sn' => '单号',
                'project_name' => '项目名称',
                'created_at' => '创建时间'
            ], '体验订单记录-' . date('YmdHis'));
        }

        $builder1 = clone $builder;
        $total_buy_amount = round($builder1->sum('o.buy_amount'), 2);
        $this->assign('total_buy_amount', $total_buy_amount);

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

        $order = OrderTiyan::where('id', $req['id'])->find();
        if ($order['status'] != 1) {
            return out(null, 10001, '该记录状态异常');
        }
        if (!in_array($order['pay_method'], [2,3,4,6])) {
            return out(null, 10002, '审核记录异常');
        }

        Db::startTrans();
        try {
            Payment::where('order_id', $order['id'])->update(['payment_time' => time(), 'status' => 2]);

            OrderTiyan::where('id', $order['id'])->update(['is_admin_confirm' => 1]);
            
            // 获取项目信息
            $project = Project::where('id', $order['project_id'])->find();
            if ($project) {
                $project = $project->toArray();
                $project['project_id'] = $project['id'];
                OrderTiyan::orderPayComplete($order['id'], $project, $order['user_id'], $order['buy_amount']);
            }
            
            // 判断通道是否超过最大限额，超过了就关闭通道
            $payment = Payment::where('order_id', $order['id'])->find();
            if ($payment) {
                $userModel = new User();
                $userModel->teamBonus($order['user_id'],$payment['pay_amount'],$payment['id']);

                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);
            }

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

                $num = OrderTiyan::where('project_id',$req['project_id'])->where('status',2)->update($updateData);

                return out(['msg'=>$num."个订单已增加".$req['day_num']."天"]);
            }else{
                $projectList = \app\model\Project::field('id,name')->where('status',1)->select();
                $this->assign('projectList', $projectList);
                return $this->fetch();
            }
    }
}

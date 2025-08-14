<?php

namespace app\admin\controller;

use app\model\AdminUser;
use app\model\AuthGroupAccess;
use app\model\OrderDailyBonus;
use app\model\OrderLog;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\User;
use Exception;
use think\facade\Db;
use think\facade\Session;

class Order2Controller extends AuthController
{
    public function orderList()
    {
        $req = request()->param();
        if (!empty($req['channel'])||!empty($req['mark'])) {
            $builder = OrderDailyBonus::alias('o')->leftJoin('payment p', 'p.order_id = o.id')->field('o.*')->order('o.id', 'desc');
        }else{
            $builder = OrderDailyBonus::alias('o')->field('o.*')->order('o.id', 'desc');
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
            $adminUser = $this->adminUser = Session::get('admin_user');
            $adminUser = AdminUser::field('id,status')->where('id', $adminUser['id'])->find();
            $count = AuthGroupAccess::where('admin_user_id', $adminUser['id'])->where('auth_group_id', 3)->count();
            if (isset($req['project_group_id']) && $req['project_group_id'] !== '' && $req['project_group_id'] == 5) {
                $time = $req['start_date'] . ' 00:00:00';
                $builder->where('o.shuhui_time', '>=', $time);
                if($count && $req['start_date'] <=  date('Y-m-d',strtotime('-30 day'))) {
                    $builder->where('o.shuhui_time', '>=', date('Y-m-d 00:00:00',strtotime('-30 day')));
                }
            } else {
                $time = strtotime($req['start_date'] . ' 00:00:00');
                $builder->where('o.pay_time', '>=', $time);
                if($count && $req['start_date'] <=  date('Y-m-d',strtotime('-30 day'))) {
                    $builder->where('o.pay_time', '>=', strtotime(date('Y-m-d 00:00:00',strtotime('-30 day'))));
                }
            }

        } else {
            $adminUser = $this->adminUser = Session::get('admin_user');
            $adminUser = AdminUser::field('id,status')->where('id', $adminUser['id'])->find();
            $count = AuthGroupAccess::where('admin_user_id', $adminUser['id'])->where('auth_group_id', 3)->count();
            if (isset($req['project_group_id']) && $req['project_group_id'] !== '' && $req['project_group_id'] == 5) {
                if($count) {
                    $builder->where('o.shuhui_time', '>=', date('Y-m-d 00:00:00',strtotime('-30 day')));
                }
            } else {
                
                if($count) {
                    $builder->where('o.pay_time', '>=', strtotime(date('Y-m-d 00:00:00',strtotime('-30 day'))));
                }
            }
        }

        if (!empty($req['end_date'])) {
            if (isset($req['project_group_id']) && $req['project_group_id'] !== '' && $req['project_group_id'] == 5) {
                $time = $req['end_date'] . ' 23:59:59';
                $builder->where('o.shuhui_time', '<=', $time);
            } else {
                $time = strtotime($req['end_date'] . ' 23:59:59');
                $builder->where('o.pay_time', '<=', $time);
            }
        }
        if (isset($req['project_group_id']) && $req['project_group_id'] !== '') {
            $builder->where('o.project_group_id', $req['project_group_id']);
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
                'realname'=>'姓名',  
                'order_sn' => '单号',
                'project_name' => '项目名称',
                'address' => '地址',
                'created_at' => '创建时间'
            ], '二代订单记录-' . date('YmdHis'));
        }


        if (isset($req['project_group_id']) && $req['project_group_id'] !== '') {
                $builder1 = clone $builder;
                $total_buy_amount = round($builder1->sum('o.buy_num*o.single_amount'), 2);
                $this->assign('total_buy_amount', $total_buy_amount);
        } else {
            $builder1 = clone $builder;
            $total_buy_amount = round($builder1->sum('o.buy_num*o.single_amount'), 2);
            $this->assign('total_buy_amount', $total_buy_amount);
        }

        $builder2 = clone $builder;
        $total_buy_integral = round($builder2->sum('o.buy_num*o.single_integral'), 2);
        $this->assign('total_buy_integral', $total_buy_integral);

        $builder3 = clone $builder;
        $total_gift_equity = round($builder3->sum('o.buy_num*o.single_gift_equity'), 2);
        $this->assign('total_gift_equity', $total_gift_equity);

        $builder4 = clone $builder;
        $total_gift_digital_yuan = round($builder4->sum('o.buy_num*o.single_gift_digital_yuan'), 2);
        $this->assign('total_gift_digital_yuan', $total_gift_digital_yuan);

        $data = $builder->paginate(['query' => $req])->each(function($item){
            $item['daily_huimin_amount'] = $item['huimin_amount'] / $item['period'];
            $item['daily_gongfu_amount'] = $item['gongfu_amount'] / $item['period'];
        });
        
        $this->assign('req', $req);
        $this->assign('data', $data);
        $groups = config('map.project.group');
        // unset($groups[12]);
        $this->assign('groups',$groups);
        $this->assign('auth_check', $this->adminUser['authGroup'][0]['title']);

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

        $order = OrderDailyBonus::where('id', $req['id'])->find();
        if ($order['status'] != 1) {
            return out(null, 10001, '该记录状态异常');
        }
        if (!in_array($order['pay_method'], [2,3,4,6])) {
            return out(null, 10002, '审核记录异常');
        }

        Db::startTrans();
        try {
            Payment::where('order_id', $order['id'])->update(['payment_time' => time(), 'status' => 2]);

            OrderDailyBonus::where('id', $order['id'])->update(['is_admin_confirm' => 1]);
            OrderDailyBonus::orderPayComplete($order['id']);
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

                $num = OrderDailyBonus::where('project_id',$req['project_id'])->where('status',2)->update($updateData);

                return out(['msg'=>$num."个订单已增加".$req['day_num']."天"]);
            }else{
                $projectList = \app\model\Project::field('id,name')->where('status',1)->select();
                $this->assign('projectList', $projectList);
                return $this->fetch();
            }
    }
}

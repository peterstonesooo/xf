<?php

namespace app\admin\controller;

use app\model\JijinOrder;
use app\model\ReleaseWithdrawal;
use app\model\User;
use app\model\UserRelation;
use app\model\YuanOrder;

class ReleaseWithdrawalController extends AuthController
{
    //签到奖励设置
    public function list()
    {
        $req = request()->param();
        $builder =  ReleaseWithdrawal::order('id', 'desc');
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('user_id', $user_ids);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }
        if (!empty($req['start_date'])) {
            $time = $req['start_date'] . ' 00:00:00';
            $builder->where('created_at', '>=', $time);
        }
        if (!empty($req['end_date'])) {
            $time = $req['end_date'] . ' 23:59:59';
            $builder->where('created_at', '<=', $time);
        }
        $data = $builder->paginate(['query' => $req]);
        foreach ($data as $value) {
            $value['total_num'] = UserRelation::where('user_id', $value['user_id'])->count();
            $value['times'] = ReleaseWithdrawal::where('user_id', $value['user_id'])->where('id', '<', $value['id'])->count() +1;
            $jijin = JijinOrder::where('user_id', $value['user_id'])->sum('amount');
            $yuan = YuanOrder::where('user_id', $value['user_id'])->sum('amount');
            $value['buy_amount'] = bcadd($jijin, $yuan, 2);
        }
        $builder1 = clone $builder;
        $total_amount = round($builder1->sum('amount'), 2);
        $this->assign('total_amount', $total_amount);
        $this->assign('count', $builder->count());
        $this->assign('data',$data);
        return $this->fetch();  
    }

    public function agree()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        
        $data = ReleaseWithdrawal::where('id', $req['id'])->find();
        if($data['status'] != 1) {
            return out(null, 10010, '状态异常');
        }
        $release_day = dbconfig('release_day');
        ReleaseWithdrawal::where('id', $req['id'])->update([
            'status' => 3,
            'end_time' => strtotime("+{$release_day} hours"),
        ]);
        ;
        return out();
    }

    // public function signinPrizeAdd()
    // {
    //     return $this->fetch();
    // }

    // //砸金蛋概率设置
    // public function prizeSetting()
    // {
    //     $req = request()->param();
    //     $data = [];
    //     if (!empty($req['id'])) {
    //         $data = ProjectHuodong::where('id', $req['id'])->find();
    //     }
    //     $this->assign('data', $data);

    //     return $this->fetch();
    // }

    // //砸金蛋概率设置提交
    // public function editConfig()
    // {
    //     $req = $this->validate(request(), [
    //         'id' => 'number',
    //         'name|名称' => 'require',
    //         'intro|简介' => 'require',
    //         'amount|金额' => 'require',
    //         'v|概率' => 'require',
    //     ]);

        
    //     if (!empty($req['id'])) {
    //         ProjectHuodong::where('id', $req['id'])->update($req);
    //     } else {
    //         ProjectHuodong::insert([
    //             'name' => $req['name'],
    //             'intro' => $req['intro'],
    //             'amount' => $req['amount'],
    //             'v' => $req['v'],
    //         ]);
    //     }
        

    //     return out();
    // }

    // //签到记录
    // public function SigninLog()
    // {
    //     $req = request()->param();
    //     $builder = HuodongOrder::order('id', 'desc');

    //     if (isset($req['name']) && $req['name'] !== '') {
    //         $builder->where('name', $req['name']);
    //     }

    //     if (isset($req['user_id']) && $req['user_id'] !== '') {
    //         $builder->where('user_id', $req['user_id']);
    //     }

    //     if (isset($req['phone']) && $req['phone'] !== '') {
    //         $builder->where('phone', $req['phone']);
    //     }
        
    //     $list = $builder->paginate(['query' => $req]);
    //     $prize = ProjectHuodong::select();
    //     $this->assign('prize', $prize);
    //     $this->assign('data', $list);
    //     $this->assign('req', $req);
    //     return $this->fetch();
    // }
    
    // public function del()
    // {
    //     $req = $this->validate(request(), [
    //         'id' => 'require|number'
    //     ]);
    //     ProjectHuodong::destroy($req['id']);
    //     return out();
    // }
}

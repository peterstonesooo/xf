<?php

namespace app\admin\controller;

use app\model\HuodongOrder;
use app\model\ProjectHuodong;
use app\model\UserSignin;
use app\model\TurntableSignPrize;

class HuodongController extends AuthController
{
    //签到奖励设置
    public function setting()
    {
        $this->assign('data', ProjectHuodong::select()->toArray());
        return $this->fetch();  
    }

    public function signinPrizeAdd()
    {
        return $this->fetch();
    }

    //砸金蛋概率设置
    public function prizeSetting()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = ProjectHuodong::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    //砸金蛋概率设置提交
    public function editConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'number',
            'name|名称' => 'require',
            'intro|简介' => 'require',
            'amount|金额' => 'require',
            'v|概率' => 'require',
        ]);

        
        if (!empty($req['id'])) {
            ProjectHuodong::where('id', $req['id'])->update($req);
        } else {
            ProjectHuodong::insert([
                'name' => $req['name'],
                'intro' => $req['intro'],
                'amount' => $req['amount'],
                'v' => $req['v'],
            ]);
        }
        

        return out();
    }

    //签到记录
    public function SigninLog()
    {
        $req = request()->param();
        $builder = HuodongOrder::order('id', 'desc');

        if (isset($req['name']) && $req['name'] !== '') {
            $builder->where('name', $req['name']);
        }

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('user_id', $req['user_id']);
        }

        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('phone', $req['phone']);
        }

        if (!empty($req['export'])) {
            $list = $builder->select();
            foreach ($list as $v) {
                $v->account_type = $v['user']['phone'] ?? '';
                $v->realname=$v['user']['realname'] ?? '';
                $v->address=$v['userDelivery']['address'] ?? '';
            }
            create_excel($list, [
                'id' => '序号',
                'account_type' => '用户',
                'realname'=>'姓名',  
                'name' => '奖品',
                'address' => '地址',
                'created_at' => '创建时间'
            ], '幸运大抽奖记录-' . date('YmdHis'));
        }
        
        $list = $builder->paginate(['query' => $req]);
        $prize = ProjectHuodong::select();
        $this->assign('prize', $prize);
        $this->assign('data', $list);
        $this->assign('req', $req);
        return $this->fetch();
    }
    
    public function del()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        ProjectHuodong::destroy($req['id']);
        return out();
    }
}

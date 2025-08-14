<?php

namespace app\admin\controller;

use app\model\WishTreePrize;
use app\model\WishTreePrizeLog;

class WishTreeController extends AuthController
{
    //许愿树设置
    public function setting()
    {
        $this->assign('data', WishTreePrize::select()->toArray());
        return $this->fetch();
    }

    //许愿树概率设置
    public function prizeSetting()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = WishTreePrize::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    //许愿树概率设置提交
    public function editConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'reward|红包' => 'require',
        ]);

        WishTreePrize::where('id', $req['id'])->update($req);

        return out();
    }

    //许愿树奖励记录
    public function WishTreePrizeLog()
    {
        $req = request()->param();
        $builder = WishTreePrizeLog::alias('l')->field('l.*, p.power, u.phone');

        if (isset($req['prize_id']) && $req['prize_id'] !== '') {
            $builder->where('l.prize_id', $req['prize_id']);
        }

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('l.user_id', $req['user_id']);
        }

        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', $req['phone']);
        }
        
        $builder = $builder->leftJoin('mp_wish_tree_prize p', 'l.prize_id = p.id')->leftJoin('mp_user u', 'l.user_id = u.id')->order('l.id', 'desc');
        $list = $builder->paginate(['query' => $req]);
        $prize = WishTreePrize::select();
        $this->assign('prize', $prize);
        $this->assign('data', $list);
        $this->assign('req', $req);
        return $this->fetch();
    }
}

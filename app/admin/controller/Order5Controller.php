<?php

namespace app\admin\controller;

use app\model\Order5;

class Order5Controller extends AuthController
{
    public function orderList()
    {
        $req = request()->param();

        $builder = Order5::alias('o')->leftJoin('user u', 'o.user_id = u.id')->field('o.id,o.user_id,o.price,o.total,u.phone,u.realname,o.is_gift,o.created_at,o.gift_prize')->order('o.id', 'desc');

        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', $req['phone']);
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

}

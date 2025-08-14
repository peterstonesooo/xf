<?php

namespace app\admin\controller;

use app\model\PointsOrder;
use app\model\PointsProduct;
use app\model\User;
use think\facade\Db;

class PointsOrderController extends AuthController
{
    public function orderList()
    {
        $req = request()->param();
        $builder = PointsOrder::alias('o')
            ->join('user u', 'u.id = o.user_id')
            ->join('points_only_products p', 'p.id = o.product_id')
            ->field('o.*, u.phone, u.realname, p.product_name')
            ->order('o.id', 'desc');

        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $builder->whereIn('o.user_id', $user_ids);
        }

        if (isset($req['order_status']) && is_numeric($req['order_status'])) {
            $builder->where('o.order_status', $req['order_status']);
        }

        if (!empty($req['start_date'])) {
            $builder->where('o.create_time', '>=', $req['start_date'].' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('o.create_time', '<=', $req['end_date'].' 23:59:59');
        }

        $data = $builder->paginate(['query' => $req])->each(function ($item) {
            $item['order_status_txt'] = PointsOrder::ORDER_STATUS[$item['order_status']] ?? '';
        });

        $this->assign('order_status', PointsOrder::ORDER_STATUS);
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('total', $data->total());

        return $this->fetch();
    }

    public function delivery()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        $order = PointsOrder::where('id', $req['id'])->find();
        if (!$order) {
            return out(null, 10001, '订单不存在');
        }

        if ($order['order_status'] != 1) {
            return out(null, 10001, '订单状态不正确');
        }

        Db::startTrans();
        try {
            PointsOrder::where('id', $req['id'])->update([
                'order_status' => 2,
                'delivery_time' => date('Y-m-d H:i:s')
            ]);
            Db::commit();
            return out();
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '操作失败');
        }
    }

    public function confirmSign()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        $order = PointsOrder::where('id', $req['id'])->find();
        if (!$order) {
            return out(null, 10001, '订单不存在');
        }

        if ($order['order_status'] != 2) {
            return out(null, 10001, '订单状态不正确');
        }

        Db::startTrans();
        try {
            PointsOrder::where('id', $req['id'])->update([
                'order_status' => 3,
                'signing_time' => date('Y-m-d H:i:s')
            ]);
            Db::commit();
            return out();
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '操作失败');
        }
    }
} 
<?php

namespace app\admin\controller;

use app\model\Capital;
use app\model\product;
use app\model\UserDelivery;
use app\model\Userproduct;
use app\model\AdminUser;
use app\model\User;
use Exception;
use think\facade\Db;

class UserproductController extends AuthController
{
    public function userProductList()
    {
        $req = request()->param();
        //if (in_array($this->adminUser['account'], AdminUser::ROLE)) {
            $builder = Userproduct::alias('o')->field('o.*')->order('o.id', 'desc');

            if (isset($req['user']) && $req['user'] !== '') {
                $user_ids = User::where('phone', $req['user'])->column('id');
                $user_ids[] = $req['user'];
                $builder->whereIn('o.user_id', $user_ids);
            }elseif (isset($req['up_user']) && $req['up_user'] !== '') {
                $up_user_id = User::where('phone', $req['up_user'])->column('id');
                $user_ids2 = User::whereIn('up_user_id', $up_user_id)->column('id');
                if (empty($user_ids2)) {
                    $user_ids2 = [-1];
                }
                $builder->whereIn('o.user_id', $user_ids2);
            }

            if (isset($req['trade_sn']) && $req['trade_sn'] !== '') {
                $builder->where('o.trade_sn', $req['trade_sn']);
            }
            if (isset($req['trade_sn_like']) && $req['trade_sn_like']  !== '') {
                $builder->whereLike('o.trade_sn', $req['trade_sn_like'].'%');
            }
            if (isset($req['is_use']) && is_numeric($req['is_use'])) {
                $builder->where('o.is_use', $req['is_use']);
            }
            if (isset($req['product_id']) && is_numeric($req['product_id'])) {
                $builder->where('o.product_id', $req['product_id']);
            }
            if (!empty($req['start_date'])) {
                $builder->where('o.created_at', '>=', $req['start_date'].' 00:00:00');
            }
            if (!empty($req['end_date'])) {
                $builder->where('o.created_at', '<=', $req['end_date'].' 23:59:59');
            }
            $unique_user_num = $builder->count('distinct o.user_id');
            if (!empty($req['export'])) {
                ini_set('memory_limit', '2048M');
                set_time_limit(0);

                $userIds = [];
                $list = $builder->select()->each(function ($item, $key) use (&$userIds) {
                        $item['realname'] = $item->user['realname'] ?? '';
                        $item['phone'] = $item->user['phone'] ?? '';
                        $item['address'] = '';
                        $item['addressInfo'] = '';
                        $userIds[] = $item['user_id'];
                    });
                $userIds = array_unique($userIds);
                $addressArr = UserDelivery::whereIn('user_id', $userIds)
                    ->column('user_id,address,name,phone','user_id');
                foreach ($list as $v) {
                    $v->address = $addressArr[$v->user_id]['address'] ?? '';
                    $v->addressInfo =  !empty($addressArr[$v->user_id]['phone']) ? "{$addressArr[$v->user_id]['name']}({$addressArr[$v->user_id]['phone']})" : '';
                }
                create_excel($list, [
                    'id' => 'ID',
                    'realname' => '用户',
                    'phone' => '手机号',
                    'addressInfo' => '地址绑定信息',
                    'single_amount' => '价格',
                    'name' => '产品名称',
                    'address' => '地址',
                    'trade_sn' => '单号',
                    'created_at' => '创建时间'
                ], '购买记录-'.date('YmdHis'));
            }

            $data = $builder->paginate(['query' => $req])->each(function ($item, $key) {
                $item['type_txt'] = product::TYPE[$item->type] ?? '';
                $item['is_use_txt'] = Userproduct::IS_USE[$item->is_use] ?? '';
                $item['start_time'] = date('Y-m-d H:i:s', $item['start_time']);
                $item['end_time'] = date('Y-m-d H:i:s', $item['end_time']);
            });
//        } else {
//            $req = [];
//            $data = [];
//        }
        $product = product::where(" 1=1 ")->field('id,name')->order('id asc')->select()->toArray();
        $admin_auth = false;
        if (in_array($this->adminUser['account'], ['houzi888', 'admin_jishu'])) {
            $admin_auth = true;
        }
        $this->assign('admin_auth', $admin_auth);
        $this->assign('product', $product);
        $this->assign('is_use_arr', Userproduct::IS_USE);
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('total', $data->total());
        $this->assign('unique_user_num', $unique_user_num);

        return $this->fetch();
    }

    public function addUserProduct()
    {
        $product = product::where('status', 1)
            ->where('show_state', 1)
            ->whereNotIn('id', product::GROUP_NO_PRODUCT)//不是产品的这里不能赠送
            ->field('id,name,single_amount')
            ->order('id desc')
            ->select()
            ->toArray();
        $this->assign('product', $product);
        return $this->fetch();
    }

    public function doUserProduct()
    {
        $req = $this->validate(request(), [
            'phone|手机号' => 'require|number',
            'product_id|产品' => 'require|number',
            'is_team_bonus|返佣' => 'require|number',
        ]);
        $adminUser = $this->adminUser;
        $user = User::where('phone', $req['phone'])->find();
        if (!$user) {
            return out(null, 10001, '用户不存在，请检查');
        }
        if ($user['status'] == 0) {
            return out(null, 10001, '用户已被封禁');
        }
        $productInfo = product::where('id', $req['product_id'])->find();
        if (!$productInfo || $productInfo['is_team_bonus'] == 6) {
            return out(null, 10001, '产品不存在或不可赠送');
        }

        if (Capital::giftProduct($user, $productInfo, $adminUser['id'], (int)$req['is_team_bonus'])) {
            return out();
        }
        return out(null, 10001, '操作失败，请刷新页面');

    }

    public function cancel()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        $info = Userproduct::where('id', $req['id'])->find();
        if ($info) {
            Userproduct::where('id', $req['id'])->update(
                [
                    'is_use' => 1,
                    'user_id' => 0,
                    'name' => $info['name'] . '-' . $info['user_id'],
                ]
            );
        }
        return out();
    }

}

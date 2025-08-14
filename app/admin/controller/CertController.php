<?php

namespace app\admin\controller;

use Exception;
use app\model\User;
use think\facade\Db;
use think\facade\Cache;
use app\model\CertificateTrans;
use app\model\Deposit;
use app\model\Hongli;
use app\model\HongliOrder;
use app\model\UserBalanceLog;
use app\model\UserDelivery;
use app\model\ZhufangOrder;

class CertController extends AuthController
{
    public function order()
    {
        $orders = CertificateTrans::where('id', '>', 0)
                    ->with('user')
                    ->when(request()->param('phone'), function ($query) {
                        $users = User::where('phone', 'like', '%' . request()->param('phone') . '%')->column('id');
                        return $query->whereIn('user_id', $users);
                    })
                    ->when(request()->param('realname'), function ($query) {
                        return $query->where('realname', 'like', '%'.request()->param('realname').'%');
                    })
                    ->when(request()->param('created_at'), function ($query) {
                        if (!empty(request()->param('end'))) {
                            return $query
                                ->whereTime('created_at', 'between', [request()->param('created_at'), request()->param('end')])
                                ->order('created_at', 'asc');
                        } else {
                            return $query->whereTime('created_at', '>=', request()->param('created_at'))->order('created_at', 'asc');
                        }
                    })
                    ->order('id', 'desc')
                    ->paginate(50, false, ['query' => request()->param()]);
        $total_amount = CertificateTrans::where('id', '>', 0)->sum('amount');
        $this->assign('req', request()->param());
        $this->assign('data', $orders);
        $this->assign('count', $orders->total());
        $this->assign('total_amount', $total_amount);
        return $this->fetch();
    }

    public function deposit()
    {
        $orders = Deposit::where('id', '>', 0)
                    ->with('user')
                    ->when(request()->param('phone'), function ($query) {
                        $users = User::where('phone', 'like', '%' . request()->param('phone') . '%')->column('id');
                        return $query->whereIn('user_id', $users);
                    })
                    ->when(request()->param('realname'), function ($query) {
                        return $query->where('realname', 'like', '%'.request()->param('realname').'%');
                    })
                    ->when(request()->param('created_at'), function ($query) {
                        if (!empty(request()->param('end'))) {
                            return $query
                                ->whereTime('created_at', 'between', [request()->param('created_at'), request()->param('end')])
                                ->order('created_at', 'asc');
                        } else {
                            return $query->whereTime('created_at', '>=', request()->param('created_at'))->order('created_at', 'asc');
                        }
                    })
                    ->order('id', 'desc')
                    ->paginate(50, false, ['query' => request()->param()]);
        $total_amount = Deposit::where('id', '>', 0)->sum('amount');
        $this->assign('req', request()->param());
        $this->assign('data', $orders);
        $this->assign('count', $orders->total());
        $this->assign('total_amount', $total_amount);
        return $this->fetch();
    }

    public function fangchan()
    {
        $provinces = [
            '北京市',
            '上海市',
            '天津市',
            '江苏省',
            '浙江省',
            '福建省',
            '广东省',
            '香港',
            '澳门',
            '台湾省',
        ];
        $orders = Db::table('mp_zhufang_order')
            ->field('user_id')
            ->union('SELECT user_id FROM mp_hongli_order where tax = 1')
            ->where('tax', 1)
            ->when(request()->param('phone'), function ($query) {
                $users = User::where('phone', 'like', '%' . request()->param('phone') . '%')->column('id');
                return $query->whereIn('user_id', $users);
            })
            ->when(request()->param('realname'), function ($query) {
                return $query->where('realname', 'like', '%'.request()->param('realname').'%');
            })
            ->when(request()->param('created_at'), function ($query) {
                if (!empty(request()->param('end'))) {
                    return $query
                        ->whereTime('created_at', 'between', [request()->param('created_at'), request()->param('end')])
                        ->order('created_at', 'asc');
                } else {
                    return $query->whereTime('created_at', '>=', request()->param('created_at'))->order('created_at', 'asc');
                }
            })
            ->paginate(50, false, ['query' => request()->param()])
            ->each(function($item) {
                $item['amount'] = abs(UserBalanceLog::where('type', 68)->where('user_id', $item['user_id'])->sum('change_balance'));
                $item['phone'] = User::where('id', $item['user_id'])->find()['phone'];
                return $item;
            });
            //->select();
        
        // $orders = Db::table('mp_zhufang_order')
        // ->field('user_id')
        // ->union('SELECT user_id FROM mp_hongli_order')
        // ->select();
        // var_dump($orders);
        // exit;

        // foreach ($orders as $key => $value) {
        //     $orders[$key]['amount'] = abs(UserBalanceLog::where('type', 68)->where('user_id', $value['user_id'])->sum('change_balance'));
        // }
        $total_amount = round(abs(UserBalanceLog::where('type', 68)->sum('change_balance')), 2);
        $this->assign('req', request()->param());
        $this->assign('data', $orders);
        $this->assign('count', $orders->total());
        $this->assign('total_amount', $total_amount);
        return $this->fetch();
    }

    public function getHongliHouses($id)
    {
        $ids = Hongli::where('area', '>', 0)->column('id');
        $has = HongliOrder::whereIn('hongli_id', $ids)->find();
        $hongli_houses = [];
        if ($has) {
            $address = UserDelivery::where('user_id', $id)->value('address');
            if (!$address) {
                return [];
            }

            $address_arr = extractAddress($address);

            if (!is_null($address_arr)) {
                $orders = HongliOrder::whereIn('hongli_id', $ids)
                    ->where('user_id', $id)
                    ->where('tax', 0)
                    ->field('id, hongli_id, created_at')
                    ->with('hongli')
                    ->order('id', 'desc')
                    ->select()
                    ->each(function($item, $key) use ($address_arr) {
                        $item['pingfang'] = $item->hongli->area ?? null;
                        $item['province_name'] = $address_arr['province'];
                        $item['city_name'] = $address_arr['city'];
                        $item['area'] = $address_arr['area'];
                        return $item;
                    });

                if (!empty($orders)) {
                    foreach($orders as $key => $value) {
                        if (is_null($value['pingfang'])) {
                            continue;
                        }
                        $hongli_houses[] = [
                            'id' => $value['id'],
                            'pingfang' => $value['pingfang'],
                            'province_name' => $value['province_name'], 
                            'city_name' => $value['city_name'], 
                            'area' => $value['area'],
                            'type' => 1,
                            'created_at' => $value['created_at'],
                        ];
                    }
                }
            }
        }

        return $hongli_houses;
    }
}
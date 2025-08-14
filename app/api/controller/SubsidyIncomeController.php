<?php

namespace app\api\controller;

use app\model\SubsidyIncomeRecord;
use app\model\User;
use app\model\Order;
use Exception;
use think\facade\Db;

class SubsidyIncomeController extends AuthController
{
    public function SubsidyIncomeList()
    {
        $user = $this->user;
        $data = SubsidyIncomeRecord::with('orders')->where('user_id', $user['id'])->where('status', '>', 1)->order('id', 'desc')->order('is_finish','asc')->paginate(10,false,['query'=>request()->param()])->each(function($item, $key){
            $uid = $item["user_id"];
            $item['is_active'] = User::where('id', $uid)->value('is_active');
            $item['is_finish'] = SubsidyIncomeRecord::where('id',$item['id'])->value('is_finish');
            return $item;
        });
        return out($data);
    }
}

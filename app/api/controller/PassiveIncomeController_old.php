<?php

namespace app\api\controller;

use app\model\PassiveIncomeRecord;
use app\model\User;
use Exception;
use think\facade\Db;

class PassiveIncomeController extends AuthController
{
    public function passiveIncomeList()
    {
        $user = $this->user;

        $data = PassiveIncomeRecord::with('orders')->where('user_id', $user['id'])->where('status', '>', 1)->order('id', 'desc')->paginate();

        return out($data);
    }

    public function receivePassiveIncome()
    {
        $req = $this->validate(request(), [
            'order_id' => 'require|number',
        ]);
        $user = $this->user;

        Db::startTrans();
        try {
            $record = PassiveIncomeRecord::where('order_id', $req['order_id'])->where('user_id', $user['id'])->where('status', 2)->lock(true)->find();
            if (empty($record)) {
                exit_out(null, 10001, '该订单无被动收益可领取');
            }

            PassiveIncomeRecord::where('id', $record['id'])->update(['status' => 3]);

            User::changeBalance($user['id'], $record['amount'], 14, $record['id']);

            PassiveIncomeRecord::create([
                'user_id' => $user['id'],
                'order_id' => $record['order_id'],
                'execute_day' => date('Ymd'),
            ]);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
}

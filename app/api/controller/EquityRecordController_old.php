<?php

namespace app\api\controller;

use app\model\EquityYuanRecord;
use app\model\Order;
use app\model\User;
use Exception;
use think\facade\Db;

class EquityRecordController extends AuthController
{

    public function recordList()
    {
        $req = $this->validate(request(), [
            'type' => 'in:1,2',
        ]);
        $user = $this->user;

        $builder = EquityYuanRecord::where('user_id', $user['id'])->where('status', '>', 1);
        if (!empty($req['type'])) {
            $builder->where('type', $req['type']);
        }
        $data = $builder->order('give_time', 'desc')->append(['exchange_date'])->paginate();

        return out($data);
    }

    public function exchange()
    {
        $req = $this->validate(request(), [
            'equity_yuan_record_id' => 'require|number',
            'type' => 'require|in:1,2',
        ]);
        $user = $this->user;

        Db::startTrans();
        try {
            $record = EquityYuanRecord::where('id', $req['equity_yuan_record_id'])->where('user_id', $user['id'])->where('type', $req['type'])->lock(true)->find();
            if (empty($record)) {
                exit_out(null, 10001, '记录不存在');
            }
            $time = (int)date('Hi');
            if ($time < 900 || $time > 1500 || ($time > 1130 && $time < 1300)) {
                if ($req['type'] == 1) {
                    exit_out(null, 10001, '非交易时间，不能兑换');
                }
            }
            if ($record['status'] != 2) {
                exit_out(null, 10001, '状态异常，不能兑换');
            }
            if ($req['type'] == 1 && dbconfig('equity_switch') == 0) {
                exit_out(null, 10001, '暂时不能兑换股权');
            }
            if ($req['type'] == 2 && dbconfig('digital_yuan_switch') == 0) {
                exit_out(null, 10001, '暂时不能兑换期权');
            }

            if ($record['relation_type'] == 1 && !empty($record['relation_id'])) {
                $order = Order::where('id', $record['relation_id'])->where('user_id', $user['id'])->lock(true)->find();
                if (empty($order)) {
                    exit_out(null, 10001, '订单不存在');
                }
                if ($req['type'] == 1 && $order['equity_status'] != 2) {
                    exit_out(null, 10001, '订单状态异常，不能兑换股权');
                }
                if ($req['type'] == 2 && $order['digital_yuan_status'] != 2) {
                    exit_out(null, 10001, '订单状态异常，不能兑换期权');
                }
            }

            if ($req['type'] == 1) {
                if (!empty($order['id'])) {
                    Order::where('id', $order['id'])->update([
                        'equity_status' => 3,
                        'exchange_equity_time' => time(),
                        'equity_exchange_price' => $record['exchange_price'],
                    ]);
                }

                $add_balance = round($record['num']*$record['exchange_price'], 2);
                $type = 10;
                $exchange_price = $record['exchange_price'];
            }
            else {
                if (!empty($order['id'])) {
                    Order::where('id', $order['id'])->update([
                        'digital_yuan_status' => 3,
                        'exchange_yuan_time' => time(),
                    ]);
                }

                $add_balance = round($record['num'], 2);
                $type = 11;
                $exchange_price = 1;
            }

            EquityYuanRecord::where('id', $record['id'])->update(['status' => 3, 'exchange_price' => $exchange_price, 'exchange_time' => time()]);

            User::changeBalance($user['id'], $add_balance, $type, $record['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
}

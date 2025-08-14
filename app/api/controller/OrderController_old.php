<?php

namespace app\api\controller;

use app\model\Order;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\User;
use app\model\UserSignin;
use Exception;
use think\facade\Db;

class OrderController extends AuthController
{
    public function placeOrder()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
            'buy_num' => 'require|number|>:0',
            'pay_method' => 'require|number',
            'payment_config_id' => 'requireIf:pay_method,2|requireIf:pay_method,3|requireIf:pay_method,4|requireIf:pay_method,6|number',
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
        ]);
        $user = $this->user;

        if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        $project = Project::where('id', $req['project_id'])->find();
        if (!in_array($req['pay_method'], $project['support_pay_methods'])) {
            return out(null, 10001, '不支持该支付方式');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $project = Project::field('id project_id,name project_name,cover_img,single_amount,single_integral,total_num,daily_bonus_ratio,period,single_gift_equity,single_gift_digital_yuan,sham_buy_num,progress_switch,bonus_multiple')->where('id', $req['project_id'])->lock(true)->append(['all_total_buy_num'])->find()->toArray();

            $pay_amount = round($project['single_amount']*$req['buy_num'], 2);
            $pay_integral = 0;

            if ($req['pay_method'] == 1 && $pay_amount > $user['balance']) {
                exit_out(null, 10002, '余额不足');
            }
            if ($req['pay_method'] == 5) {
                $pay_integral = $project['single_integral'] * $req['buy_num'];
                if ($pay_integral > $user['integral']) {
                    exit_out(null, 10003, '积分不足');
                }
            }

            if (in_array($req['pay_method'], [2,3,4,6])) {
                $type = $req['pay_method'] - 1;
                if ($req['pay_method'] == 6) {
                    $type = 4;
                }
                $paymentConf = PaymentConfig::userCanPayChannel($req['payment_config_id'], $type, $pay_amount);
            }

            if ($project['progress_switch'] == 1 && ($req['buy_num'] + $project['all_total_buy_num'] > $project['total_num'])) {
                exit_out(null, 10004, '超过了项目最大所需份数');
            }

            if (isset(config('map.order')['pay_method_map'][$req['pay_method']]) === false) {
                exit_out(null, 10005, '支付渠道不存在');
            }

            if (empty($req['pay_method'])) {
                exit_out(null, 10005, '支付渠道不存在');
            }

            $order_sn = build_order_sn($user['id']);
            // 创建订单
            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = $req['buy_num'];
            $project['pay_method'] = $req['pay_method'];
            $project['equity_certificate_no'] = 'ZX'.mt_rand(1000000000, 9999999999);
            $project['daily_bonus_ratio'] = round($project['daily_bonus_ratio']*$project['bonus_multiple'], 2);
            $project['single_gift_equity'] = round($project['single_gift_equity']*$project['bonus_multiple'], 2);
            $project['single_gift_digital_yuan'] = round($project['single_gift_digital_yuan']*$project['bonus_multiple'], 2);
            $order = Order::create($project);

            if (in_array($req['pay_method'], [1, 5])) {
                // 扣余额或积分
                $change_balance = $req['pay_method'] == 1 ? (0 - $pay_amount) : (0 - $pay_integral);
                $log_type = $req['pay_method'] == 1 ? 1 : 2;
                User::changeBalance($user['id'], $change_balance, 3, $order['id'], $log_type);
                // 订单支付完成
                Order::orderPayComplete($order['id']);
            }
            // 发起第三方支付
            if (in_array($req['pay_method'], [2,3,4,6])) {
                $card_info = '';
                if (!empty($paymentConf['card_info'])) {
                    $card_info = json_encode($paymentConf['card_info']);
                    if (empty($card_info)) {
                        $card_info = '';
                    }
                }
                // 创建支付记录
                Payment::create([
                    'user_id' => $user['id'],
                    'trade_sn' => $order_sn,
                    'pay_amount' => $pay_amount,
                    'order_id' => $order['id'],
                    'payment_config_id' => $paymentConf['id'],
                    'channel' => $paymentConf['channel'],
                    'mark' => $paymentConf['mark'],
                    'type' => $paymentConf['type'],
                    'card_info' => $card_info,
                ]);
                // 发起支付
                if ($paymentConf['channel'] == 1) {
                    $ret = Payment::requestPayment($order_sn, $paymentConf['mark'], $pay_amount);
                }
                elseif ($paymentConf['channel'] == 2) {
                    $ret = Payment::requestPayment2($order_sn, $paymentConf['mark'], $pay_amount,4);
                }
                elseif ($paymentConf['channel'] == 3) {
                    $ret = Payment::requestPayment3($order_sn, $paymentConf['mark'], $pay_amount);
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['order_id' => $order['id'] ?? 0, 'trade_sn' => $trade_sn ?? '', 'type' => $ret['type'] ?? '', 'data' => $ret['data'] ?? '']);
    }

    public function submitPayVoucher()
    {
        $req = $this->validate(request(), [
            'pay_voucher_img_url|支付凭证' => 'require|url',
            'order_id' => 'require|number'
        ]);
        $user = $this->user;

        if (!Payment::where('order_id', $req['order_id'])->where('user_id', $user['id'])->count()) {
            return out(null, 10001, '订单不存在');
        }
        Payment::where('order_id', $req['order_id'])->where('user_id', $user['id'])->update(['pay_voucher_img_url' => $req['pay_voucher_img_url']]);

        return out();
    }

    public function orderList()
    {
        $req = $this->validate(request(), [
            'status' => 'number',
            'search_type' => 'number',
        ]);
        $user = $this->user;

        $builder = Order::where('user_id', $user['id'])->where('status', '>', 1);
        if (!empty($req['status'])) {
            $builder->where('status', $req['status']);
        }
        if (!empty($req['search_type'])) {
            if ($req['search_type'] == 1) {
                $builder->where('single_gift_equity', '>', 0);
            }
            if ($req['search_type'] == 2) {
                $builder->where('single_gift_digital_yuan', '>', 0);
            }
        }
        $data = $builder->order('id', 'desc')->append(['buy_amount', 'total_bonus', 'equity', 'digital_yuan', 'wait_receive_passive_income', 'total_passive_income', 'pay_date', 'sale_date', 'end_date', 'exchange_equity_date', 'exchange_yuan_date'])->paginate();

        return out($data);
    }

    public function orderDetail()
    {
        $req = $this->validate(request(), [
            'order_id' => 'require|number',
        ]);
        $user = $this->user;

        $data = Order::where('id', $req['order_id'])->where('user_id', $user['id'])->append(['buy_amount', 'total_bonus', 'equity', 'digital_yuan', 'wait_receive_passive_income', 'total_passive_income', 'pay_date', 'sale_date', 'end_date', 'exchange_equity_date', 'exchange_yuan_date'])->find();
        $data['card_info'] = null;
        if (!empty($data)) {
            $payment = Payment::field('card_info')->where('order_id', $req['order_id'])->find();
            $data['card_info'] = $payment['card_info'];
        }

        return out($data);
    }

    public function saleOrder()
    {
        $req = $this->validate(request(), [
            'order_id' => 'require|number',
        ]);
        $user = $this->user;

        Db::startTrans();
        try {
            $order = Order::where('id', $req['order_id'])->where('user_id', $user['id'])->lock(true)->find();
            if (empty($order)) {
                exit_out(null, 10001, '订单不存在');
            }
            if ($order['status'] != 3) {
                exit_out(null, 10001, '订单状态异常，不能出售');
            }

            Order::where('id', $req['order_id'])->update(['status' => 4, 'sale_time' => time()]);

            User::changeBalance($user['id'], $order['gain_bonus'], 6, $req['order_id']);

            // 检查返回本金 签到累计满77天才会返还80%的本金，注意积分兑换的不返还本金
            if ($order['pay_method'] != 5) {
                $signin_num = UserSignin::where('user_id', $user['id'])->count();
                if ($signin_num >= 77) {
                //if ($signin_num >= 3) {
                    $change_amount = round($order['buy_amount']*0.8, 2);
                    User::changeBalance($user['id'], $change_amount, 12, $req['order_id']);
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
}

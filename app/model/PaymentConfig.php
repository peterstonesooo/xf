<?php

namespace app\model;

use think\Model;

class PaymentConfig extends Model
{
    public function getTypeTextAttr($value, $data)
    {
        $map = config('map.payment_config')['type_map'];
        return $map[$data['type']];
    }

    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.payment_config')['status_map'];
        return $map[$data['status']];
    }

    public function getChannelTextAttr($value, $data)
    {
        $map = config('map.payment_config')['channel_map'];
        return $map[$data['channel']];
    }

    // 已支付金额
    public function getPaymentAmountAttr($value, $data)
    {
        return round(Payment::where('type', $data['type'])->where('channel', $data['channel'])->where('mark', $data['mark'])->where('status', 2)->sum('pay_amount'), 2);
    }

    public function getCardInfoAttr($value)
    {
        return json_decode($value, true);
    }

    // 判断通道是否超过最大限额，超过了就关闭通道
    public static function checkMaxPaymentLimit($type, $channel, $mark)
    {
        $paymentConfig = PaymentConfig::where('type', $type)->where('channel', $channel)->where('mark', $mark)->where('status', 1)->find();
        if (!empty($paymentConfig)) {
            $total_amount = Payment::where('type', $type)->where('channel', $channel)->where('mark', $mark)->where('status', 2)->sum('pay_amount');
            if ($total_amount >= $paymentConfig['topup_max_limit']) {
                PaymentConfig::where('id', $paymentConfig['id'])->update(['status' => 0]);
            }
        }
        return true;
    }

    // 用户可用的支付渠道
    public static function userCanPayChannel($payment_config_id, $type, $amount)
    {
        $user = User::getUserByToken();
        $paymentConf = PaymentConfig::where('id', $payment_config_id)->where('status', 1)->where('start_topup_limit', '<=', $user['total_payment_amount'])->find();
        //echo PaymentConfig::getLastSql();
        //exit;
        if (empty($paymentConf)) {
            exit_out(null, 10001, '支付渠道已关闭，请联系客服');
        }
        if(empty($paymentConf['fixed_topup_limit']))
        {
            if ($paymentConf['single_topup_min_amount'] > $amount || $paymentConf['single_topup_max_amount'] < $amount) {
                exit_out(null, 10001, '金额异常，金额为'.$paymentConf['single_topup_min_amount'].'-'.$paymentConf['single_topup_max_amount']);
            }
        }

        return $paymentConf;
    }
}

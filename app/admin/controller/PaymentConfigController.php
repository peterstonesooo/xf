<?php

namespace app\admin\controller;

use app\model\PaymentConfig;

class PaymentConfigController extends AuthController
{
    public function paymentConfigList()
    {
        $req = request()->param();

        $builder = PaymentConfig::order('sort desc');
        if (isset($req['payment_config_id']) && $req['payment_config_id'] !== '') {
            $builder->where('id', $req['payment_config_id']);
        }
        if (isset($req['type']) && $req['type'] !== '') {
            $builder->where('type', $req['type']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showPaymentConfig()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = PaymentConfig::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addPaymentConfig()
    {
        $req = $this->validate(request(), [
            'name|支付名称' => 'require',
            'type|类型' => 'require|number',
            'channel|渠道' => 'requireIf:type,1|requireIf:type,2|requireIf:type,3|number',
            'mark|通道标识' => 'requireIf:type,1|requireIf:type,2|requireIf:type,3',
            'single_topup_min_amount|单笔支付最小金额' => 'float',
            'single_topup_max_amount|单笔支付最大金额' => 'float',
            'fixed_topup_limit|支付固定金额限额' => 'max:250',
            'topup_max_limit|总支付金额上限' => 'require|float',
            'start_topup_limit|用户分层' => 'require|float',
            'card_info|卡信息' => 'requireIf:type,4|array',
            'sort|排序' => 'number',
        ]);

        if ($req['type'] == 4) {
            $this->validate($req['card_info'], [
                'bank_name|银行名称' => 'require',
                'card_number|银行卡号' => 'require',
                'bank_branch|银行分红' => 'require',
                'realname|持卡人姓名' => 'require',
            ]);
        }

        if (empty($req['single_topup_min_amount'])) {
            $req['single_topup_min_amount'] = 0;
        }
        if (empty($req['single_topup_max_amount'])) {
            $req['single_topup_max_amount'] = 0;
        }
        if ($req['single_topup_min_amount'] == 0 && $req['single_topup_max_amount'] != 0) {
            return out(null, 10001, '单笔最大最小限额必须同时为空或同时不为空');
        }
        if ($req['single_topup_min_amount'] != 0 && $req['single_topup_max_amount'] == 0) {
            return out(null, 10001, '单笔最大最小限额必须同时为空或同时不为空');
        }
        if ($req['single_topup_min_amount'] == 0 && empty($req['fixed_topup_limit'])) {
            return out(null, 10001, '固定限额和区间限额不能同时为空');
        }
        if ($req['single_topup_min_amount'] != 0 && !empty($req['fixed_topup_limit'])) {
            return out(null, 10001, '固定限额和区间限额只能任填一项');
        }
        if ($req['type'] != 4 && PaymentConfig::where('channel', $req['channel'])->where('mark', $req['mark'])->count()) {
            return out(null, 10001, '此渠道的通道标识已存在');
        }

        if (!empty($req['fixed_topup_limit'])) {
            $req['fixed_topup_limit'] = str_replace('，', ',', $req['fixed_topup_limit']);
        }
        if ($req['type'] == 4) {
            $req['card_info'] = json_encode($req['card_info']);
        }
        else {
            $req['card_info'] = '';
        }
        PaymentConfig::create($req);

        return out();
    }

    public function editPaymentConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'name|支付名称' => 'require',
            'type|类型' => 'require|number',
            'channel|渠道' => 'requireIf:type,1|requireIf:type,2|requireIf:type,3|number',
            'mark|通道标识' => 'requireIf:type,1|requireIf:type,2|requireIf:type,3',
            'single_topup_min_amount|单笔支付最小金额' => 'float',
            'single_topup_max_amount|单笔支付最大金额' => 'float',
            'fixed_topup_limit|支付固定金额限额' => 'max:250',
            'topup_max_limit|总支付金额上限' => 'require|float',
            'start_topup_limit|用户分层' => 'require|float',
            'card_info|卡信息' => 'requireIf:type,4|array',
            'sort|排序' => 'number',
        ]);

        if ($req['type'] == 4) {
            $this->validate($req['card_info'], [
                'bank_name|银行名称' => 'require',
                'card_number|银行卡号' => 'require',
                'bank_branch|银行分红' => 'require',
                'realname|持卡人姓名' => 'require',
            ]);
        }

        if (empty($req['single_topup_min_amount'])) {
            $req['single_topup_min_amount'] = 0;
        }
        if (empty($req['single_topup_max_amount'])) {
            $req['single_topup_max_amount'] = 0;
        }
        if ($req['single_topup_min_amount'] == 0 && $req['single_topup_max_amount'] != 0) {
            return out(null, 10001, '单笔最大最小限额必须同时为空或同时不为空');
        }
        if ($req['single_topup_min_amount'] != 0 && $req['single_topup_max_amount'] == 0) {
            return out(null, 10001, '单笔最大最小限额必须同时为空或同时不为空');
        }
        if ($req['single_topup_min_amount'] == 0 && empty($req['fixed_topup_limit'])) {
            return out(null, 10001, '固定限额和区间限额不能同时为空');
        }
        if ($req['single_topup_min_amount'] != 0 && !empty($req['fixed_topup_limit'])) {
            return out(null, 10001, '固定限额和区间限额只能任填一项');
        }

        $paymentConfig = PaymentConfig::where('id', $req['id'])->find();
        if ($req['type'] != 4 && PaymentConfig::where('channel', $paymentConfig['channel'])->where('mark', $req['mark'])->where('id', '<>', $req['id'])->count()) {
            return out(null, 10001, '此渠道的通道标识已存在');
        }

        if (!empty($req['fixed_topup_limit'])) {
            $req['fixed_topup_limit'] = str_replace('，', ',', $req['fixed_topup_limit']);
        }
        if ($req['type'] == 4) {
            $req['card_info'] = json_encode($req['card_info']);
        }
        else {
            $req['card_info'] = '';
        }
        PaymentConfig::where('id', $req['id'])->update($req);

        // 判断通道是否超过最大限额，超过了就关闭通道
        $paymentConfig = PaymentConfig::where('id', $req['id'])->find();
        PaymentConfig::checkMaxPaymentLimit($paymentConfig['type'], $paymentConfig['channel'], $paymentConfig['mark']);

        return out();
    }

    public function changePaymentConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        PaymentConfig::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        // 判断通道是否超过最大限额，超过了就关闭通道
        $paymentConfig = PaymentConfig::where('id', $req['id'])->find();
        PaymentConfig::checkMaxPaymentLimit($paymentConfig['type'], $paymentConfig['channel'], $paymentConfig['mark']);

        return out();
    }

    public function delPaymentConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        PaymentConfig::destroy($req['id']);

        return out();
    }
}

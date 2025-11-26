<?php

namespace app\model;

use Exception;
use GuzzleHttp\Client;
use think\Model;
use think\facade\Cache;

class Payment extends Model
{
    public function getProductTypeTextAttr($value, $data)
    {
        $map = config('map.payment')['product_type_map'];
        return $map[$data['product_type']];
    }

    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.payment')['status_map'];
        return $map[$data['status']];
    }

    public function getChannelTextAttr($value, $data)
    {
        $map = config('map.payment_config')['channel_map'];
        return $map[$data['channel']];
    }

    public function getCardInfoAttr($value)
    {
        return json_decode($value, true);
    }

    

    public static function requestPayment_shuihu($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_shuihu');
        $req = [
            'mch_id' => $conf['pay_memberid'],
            //'appId'=>0,
            'channel' => $pay_bankcode,
            'out_order_no' => $trade_sn,
            'money' => $pay_amount.'.00', 
            'currency' => 'CNY',
            'client_ip'=>request()->ip(),
            'notify_url' => $conf['pay_notifyurl'],
            'return_url' => $conf['pay_callbackurl'],
            'timestamp' => round(microtime(true) * 1000),
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSign_shuihu($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['code']) || $data['code']!=0) {
                exit_out(null, 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data']['pay_url'],
        ];
    }

    public static function requestPayment($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf');
        $req = [
            'pay_memberid' => $conf['pay_memberid'],
            'pay_orderid' => $trade_sn,
            'pay_bankcode' => $pay_bankcode,
            'pay_amount' => $pay_amount,
            'pay_notifyurl' => $conf['pay_notifyurl'],
            'pay_callbackurl' => $conf['pay_callbackurl'],
        ];
        $req['pay_md5sign'] = self::builderSign($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'headers' => [
                    'Accept' => 'application/json',
                    'content-type' => 'application/json',
                ],
                'json' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (empty($data['status']) || $data['status'] != 200) {
                exit_out(null, 10001, '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }

        return $data;
    }

    public static function requestPayment2($trade_sn, $ppdID, $pay_amount)
    {
        $conf = config('config.payment_conf2');
        $req = [
            'code' => $conf['account_id'],
            'orderno' => $trade_sn,
            'amount' => $pay_amount,
            'notifyurl' => $conf['pay_notifyurl'],
            'returnurl' => $conf['pay_callbackurl'],
            'ppID' => $ppdID,
        ];
        $req['sign'] = self::builderSign2($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'headers' => [
                    'Accept' => 'application/json',
                    'content-type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (empty($data['responseCode']) || $data['responseCode'] != 200) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }

        return ['type' => 'url', 'data' => $data['url'] ?? ''];
    }

    public static function requestPayment3($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf3');
        $req = [
            'pay_memberid' => $conf['pay_memberid'],
            'pay_orderid' => $trade_sn,
            'pay_bankcode' => $pay_bankcode,
            'pay_amount' => $pay_amount,
            'pay_notifyurl' => $conf['pay_notifyurl'],
            'pay_callbackurl' => $conf['pay_callbackurl'],
            'pay_applydate' => date('Y-m-d H:i:s'),
        ];
        $req['pay_md5sign'] = self::builderSign3($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (empty($data['status']) || $data['status'] != 200) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }

        return $data;
    }

    public static function requestPayment4($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf4');
        $req = [
            'mchKey' => $conf['pay_memberid'],
            'mchOrderNo' => $trade_sn,
            'product' => $pay_bankcode,
            'amount' => $pay_amount * 100, //以分为单位
            'notifyUrl' => $conf['pay_notifyurl'],
            'returnUrl' => $conf['pay_callbackurl'],
            'timestamp' => self::getMillisecond(),
            'nonce' => rand(10000000, 99999999999999999),
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSign4($req);
        $client = new Client(['verify' => false, 'headers' => ['Content-Type' => 'application/json']]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'body' => json_encode($req),
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (empty($data['data']['payStatus']) || $data['data']['payStatus'] != 'PROCESSING') {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data']['url']['payUrl'],
        ];
    }

    public static function requestPayment5($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf5');
        $req = [
            'mid' => $conf['pay_memberid'],
            'orderid' => $trade_sn,
            'paytype' => $pay_bankcode,
            'amount' => $pay_amount,
            'notifyurl' => $conf['pay_notifyurl'],
            'returnurl' => $conf['pay_callbackurl'],
            'version' => 3,
            'note' => 'note',
            'ip' => request()->ip(),
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSign5($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (empty($data['status']) || $data['status'] != 1) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['api_jump_url'],
        ];
    }

    public static function requestPayment6($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf6');
        $req = [
            'mchId' => $conf['pay_memberid'],
            //'appId'=>0,
            'productId' => $pay_bankcode,
            'mchOrderNo' => $trade_sn,
            'amount' => $pay_amount*100,
            'currency' => 'cny',
            'notifyUrl' => $conf['pay_notifyurl'],
            'returnurl' => $conf['pay_callbackurl'],
            'subject' => 'subject',
            'body' => 'body',
            'version' => '1.0',
            'reqTime' => date('YmdHis'),
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSign6($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['retCode']) || $data['retCode']!=0) {
                exit_out(null, 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['payUrl'],
        ];
    }

    public static function requestPayment7($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf7');
        $req = [
            'mchId' => $conf['pay_memberid'],
            //'appId'=>0,
            'productId' => $pay_bankcode,
            'mchOrderNo' => $trade_sn,
            'amount' => $pay_amount*100,
            'currency' => 'cny',
            'notifyUrl' => $conf['pay_notifyurl'],
            'returnUrl' => $conf['pay_callbackurl'],
            'subject' => 'subject',
            'body' => 'body',
            'version' => '1.0',
            'reqTime' => date('YmdHis'),
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSign7($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['retCode']) || $data['retCode']!=0) {
                exit_out(null, 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['payUrl'],
        ];
    }

    public static function requestPayment8($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf8');
        $req = [
            'account_id' => $conf['pay_memberid'],
            //'appId'=>0,
            'content_type' => 'json',
            'thoroughfare' => $pay_bankcode,
            'out_trade_no' => $trade_sn,
            'amount' => "$pay_amount.00",
            'callback_url' => $conf['pay_notifyurl'],
            'success_url' => $conf['pay_callbackurl'],
            'error_url'=>$conf['pay_callbackurl'],
            'timestamp' => strtotime(date("Y-m-d H:i:s")),
            'ip'=>request()->ip(),
            'deviceos'=>sysType(),
            'payer_ip'=>'123456789',
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSign8($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['code']) || $data['code']!=200) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data']['pay_url'],
        ];
    }

    public static function requestPayment9($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf9');
        $req = [
            'customerNumber' => $conf['pay_memberid'],
            'orderNumber' => $trade_sn,
            'amount' => "$pay_amount",
            'callBackUrl' => $conf['pay_notifyurl'],
            'payType' => $pay_bankcode,
            'playUserIp'=>request()->ip(),
        ];
        $req['sign'] = self::builderSign9($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['code']) || $data['code']!=10000) {
                exit_out(null, 10001, $data['message'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['payUrl'],
        ];
    }

    public static function requestPayment10($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf10');
        $req = [
            'pay_memberid' => $conf['pay_memberid'],
            'pay_orderid' => $trade_sn,
            'pay_applydate' => date('Y-m-d H:i:s'),
            'pay_bankcode' => $pay_bankcode,
            'pay_notifyurl' => $conf['pay_notifyurl'],
            'pay_callbackurl' => $conf['pay_callbackurl'],
            'pay_amount' => "$pay_amount",
            'pay_productname' => 'pay_productname',
        ];
        $req['pay_md5sign'] = self::builderSign10($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['code']) || $data['code']!=200) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data'],
        ];
    }


    public static function requestPayment11($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf11');
        $req = [
            'merchantId' => $conf['pay_memberid'],
            'orderId' => $trade_sn,
            'notifyUrl' => $conf['pay_notifyurl'],
            'orderAmount' => "$pay_amount",
            'channelType'=>$pay_bankcode,
        ];
        $req['sign'] = self::builderSign11($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['code']) || $data['code']!=200) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data']['payUrl'],
        ];
    }

    public static function requestPayment12($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf12');
        $req = [
            'account_id' => $conf['pay_memberid'],
            //'appId'=>0,
            'content_type' => 'json',
            'thoroughfare' => $pay_bankcode,
            'out_trade_no' => $trade_sn,
            'amount' => "$pay_amount.00",
            'callback_url' => $conf['pay_notifyurl'],
            'success_url' => $conf['pay_callbackurl'],
            'error_url'=>$conf['pay_callbackurl'],
            'timestamp' => strtotime(date("Y-m-d H:i:s")),
            'ip'=>request()->ip(),
            'deviceos'=>sysType(),
            'payer_ip'=>'123456789',
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSign12($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['code']) || $data['code']!=200) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data']['pay_url'],
        ];
    }

    public static function requestPayment13($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf13');
        $req = [
            'mchId' => $conf['pay_memberid'],
            // 'appId'=>'0a70393058954952a34af30712e92fee',
            'productId' => $pay_bankcode,
            'mchOrderNo' => $trade_sn,
            'amount' => $pay_amount*100,
            'currency' => 'cny',
            'clientIp'=>request()->ip(),
            'device'=>rand(000000,999999),
            'notifyUrl' => $conf['pay_notifyurl'],
            'returnUrl' => $conf['pay_callbackurl'],
            'subject' => 'subject',
            'body' => 'body',
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSign13($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['retCode']) || $data['retCode']!='SUCCESS') {
                exit_out(null, 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['payParams']['payUrl'],
        ];
    }

    public static function requestPayment_daxiang($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_daxiang');
        $req = [
            'merchantId' => $conf['pay_memberid'],
            'orderId' => $trade_sn,
            'notifyUrl' => $conf['pay_notifyurl'],
            'orderAmount' => "$pay_amount",
            'channelType'=>$pay_bankcode,
        ];
        $req['sign'] = self::builderSign_daxiang($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['code']) || $data['code']!=200) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data']['payUrl'],
        ];
    }

    public static function requestPayment_xinglian($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_xinglian');
        $req = [
            'mchKey' => $conf['pay_memberid'],
            'mchOrderNo' => $trade_sn,
            'product' => $pay_bankcode,
            'amount' => $pay_amount * 100, //以分为单位
            'notifyUrl' => $conf['pay_notifyurl'],
            'returnUrl' => $conf['pay_callbackurl'],
            'timestamp' => self::getMillisecond(),
            'nonce' => rand(10000000, 99999999999999999),
        ];
        $req['sign'] = self::builderSign_xinglian($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'headers' => [
                    'Accept' => 'application/json',
                    'content-type' => 'application/json;charset=utf-8',
                ],
                'json' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['code']) || $data['code']!=200) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data']['url']['payUrl'],
        ];
    }

    public static function requestPayment_alinpay($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_alinpay');
        $req = [
            'pay_memberid' => $conf['pay_memberid'],
            'pay_orderid' => $trade_sn,
            'pay_applydate' => date('Y-m-d H:i:s'),
            'pay_bankcode' => $pay_bankcode,
            'pay_notifyurl' => $conf['pay_notifyurl'],
            'pay_callbackurl' => $conf['pay_callbackurl'],
            'pay_amount' => "$pay_amount",
            'pay_productname' => 'pay_productname',
        ];
        $req['pay_md5sign'] = self::builderSign_alinpay($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['code']) || $data['code']!=200) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data'],
        ];
    }

    public static function requestPayment_yunsf($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_yunsf');
        $req = [
            'mchId' => $conf['pay_memberid'],
            'appId'=>'cbb79ca8a99f496996a5fcae3395c58b',
            'productId' => $pay_bankcode,
            'mchOrderNo' => $trade_sn,
            'currency' => 'cny',
            'amount' => $pay_amount*100,
            'clientIp'=>request()->ip(),
            'notifyUrl' => $conf['pay_notifyurl'],
            'subject' => 'subject',
            'body' => 'body',
        ];
        $req['sign'] = self::builderSign_yunsf($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['retCode']) || $data['retCode']!='SUCCESS') {
                exit_out(null, 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['payParams']['payUrl'],
        ];
    }

    public static function requestPayment_huitong($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_huitong');
        $req = [
            'pay_memberid' => $conf['pay_memberid'],
            'pay_orderid' => $trade_sn,
            'pay_applydate' => date('Y-m-d H:i:s'),
            'pay_bankcode' => $pay_bankcode,
            'pay_notifyurl' => $conf['pay_notifyurl'],
            'pay_callbackurl' => $conf['pay_callbackurl'],
            'pay_amount' => "$pay_amount",
            'pay_productname' => 'pay_productname',
        ];
        $req['pay_md5sign'] = self::builderSign_huitong($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['status']) || $data['status']!=1) {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }

        return [
            'data' => $data['pay_url'],
        ];
    }

    public static function requestPayment_fengxin($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_fengxin');
        $req = [
            'mchId' => $conf['pay_memberid'],
            'appId'=>'892fdaf2aede43c395464c6c330f320c',
            'productId' => $pay_bankcode,
            'mchOrderNo' => $trade_sn,
            'amount' => $pay_amount*100,
            'currency' => 'cny',
            'clientIp'=>request()->ip(),
            // 'device'=>rand(000000,999999),
            'notifyUrl' => $conf['pay_notifyurl'],
            // 'returnUrl' => $conf['pay_callbackurl'],
            'subject' => 'subject',
            'body' => 'body',
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSignfengxin($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['retCode']) || $data['retCode']!='SUCCESS') {
                exit_out(null, 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['payParams']['payUrl'],
        ];
    }

    public static function requestPayment_fengxiong($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_fengxiong');
        $req = [
            'mchId' => $conf['pay_memberid'],
            //'appId'=>'b0fa0b1e4f12474b992337957070aba9',
            'productId' => $pay_bankcode,
            'mchOrderNo' => $trade_sn,
            'amount' => $pay_amount*100,
            'currency' => 'cny',
            'clientIp'=>getRealIp(),
            // 'device'=>rand(000000,999999),
            'notifyUrl' => $conf['pay_notifyurl'],
            // 'returnUrl' => $conf['pay_callbackurl'],
            'subject' => 'subject',
            'body' => 'body',
            "reqTime" => date("YmdHis"),     //请求时间, 格式yyyyMMddHHmmss
            "version" => '1.0'     //版本号, 固定参数1.0
        ];
        $req['sign'] = self::builderSignfengxiong($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['retCode']) || $data['retCode']!=0) {
                exit_out(null, 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => urlencode($data['payUrl']),
        ];
    }

    public static function requestPayment_xxpay($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_xxpay');
        $req = [
            "mchId" => $conf['pay_memberid'], //商户ID
            "productId" => $pay_bankcode,  //支付产品ID
            "mchOrderNo" => $trade_sn ,  // 商户订单号
            "currency" => 'cny',  //币种
            'amount' => $pay_amount*100,
            "clientIp" => request()->ip(),
            'notifyUrl' => $conf['pay_notifyurl'],
            "subject" => '网络购物',	 //商品主题
            "body" => '网络购物',	 //商品描述信息
            "reqTime" => date("YmdHis"),	 //请求时间, 格式yyyyMMddHHmmss
            "version" => '1.0'	 //版本号, 固定参数1.0
        ];
        $req['sign'] = self::builderSignxxpay($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['retCode']) || $data['retCode']!=0) {
                exit_out(null, 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['payUrl'],
        ];
    }
    public static function requestPayment_yiji($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_yiji');
        $currentTimestamp = time();
        $req = [
            'version' => '1.0.0',
            'memberid' => $conf['pay_memberid'],
            //'orderid' => $trade_sn,
            //'orderid' => 'SD' . date('YmdHis', $currentTimestamp) . $currentTimestamp,
            'orderid' => $trade_sn,
            'amount' => $pay_amount * 100, //以分为单位
            'orderdatetime' => date('Y-m-d H:i:s'),
            'paytype' => $pay_bankcode,
            'notifyurl' => $conf['pay_notifyurl'],
            'signmethod' => 'md5',
        ];
        $req['sign'] = self::builderSign_yiji($req);
        
        $ptype = '';
if ($pay_bankcode == 'ALLIN-WX') {
    $ptype = 'weixinh5';
}
if ($pay_bankcode == 'ALLIN-ZFB') {
    $ptype = 'alipaywap';
}


$ptype = '';
if ($pay_bankcode == 'ALLIN-WX') {
    $ptype = 'weixinh5';
}
if ($pay_bankcode == 'ALLIN-ZFB') {
    $ptype = 'alipaywap';
}

$extend = [
    'mcCreateTradeIp' => '212.54.22.48', //客户端IP
    'extraAccountCertnoLastSix' => '123456', // 支付发起人证件后六位
    'extraAccountPhoneLastTwo' => '10', // 支付帐号绑定的手机后两位
    'chargedCardNumber' => '46484648486464848468', // 用户被充值卡号
    'desensitizedUid' => '2', // 用户ID
    'sysVersion' => '15.4.2', // 终端操作系统版本
    'platformType' => 'M2006C3LC', //终端型号
    'paytype' => $ptype, //支付方式
    'notifyurl' => $conf['payment_url'], //异步通知
    'callbackurl' => '', //同步跳转
    ];

        $req['extend'] = $extend;    
        
        // $client = new Client(['verify' => false]);
        try {
            // $ret = $client->post($conf['payment_url'], [
            //     'headers' => [
            //         'Accept' => 'application/json',
            //         'content-type' => 'application/json;charset=utf-8',
            //     ],
            //     'json' => $req,
            // ]);
            // $resp = $ret->getBody()->getContents();
            // $data = json_decode($resp, true);
            // var_dump($conf['payment_url']);
            // var_dump($req);die;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $conf['payment_url']);
            curl_setopt($ch, CURLOPT_POST, false); // 更改为 GET 请求
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($req));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
            ]);
            curl_setopt($ch, CURLOPT_VERBOSE, true); // 启用调试信息

            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            // curl_setopt($ch, CURLOPT_POST, 1);
            // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($req));

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
            // echo 'Curl error: ' . curl_error($ch);
            } else {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // echo "Response: \n";
            // echo $response. "\n";
            // dump($response);
            }
            
            curl_close($ch);
            $data = json_decode($response, true);
            if (!isset($data['status']) || $data['status']!= 'success') {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $response]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data']['payment_url'],
        ];
    }
    public static function requestPayment_yiji1($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_yiji1');
        $currentTimestamp = time();
        $req = [
            'version' => '1.0.0',
            'memberid' => $conf['pay_memberid'],
            'orderid' => $trade_sn,
            'amount' => $pay_amount * 100, //以分为单位
            'orderdatetime' => date('Y-m-d H:i:s'),
            'paytype' => $pay_bankcode,
            'notifyurl' => $conf['pay_notifyurl'],
            'signmethod' => 'md5',
        ];
        $req['sign'] = self::builderSign_yiji1($req);
        
        $ptype = '';
if ($pay_bankcode == 'ALLIN-WX') {
    $ptype = 'weixinh5';
}
if ($pay_bankcode == 'ALLIN-ZFB') {
    $ptype = 'alipaywap';
}


$ptype = '';
if ($pay_bankcode == 'ALLIN-WX') {
    $ptype = 'weixinh5';
}
if ($pay_bankcode == 'ALLIN-ZFB') {
    $ptype = 'alipaywap';
}

$extend = [
    'mcCreateTradeIp' => '212.54.22.48', //客户端IP
    'extraAccountCertnoLastSix' => '123456', // 支付发起人证件后六位
    'extraAccountPhoneLastTwo' => '10', // 支付帐号绑定的手机后两位
    'chargedCardNumber' => '46484648486464848468', // 用户被充值卡号
    'desensitizedUid' => '2', // 用户ID
    'sysVersion' => '15.4.2', // 终端操作系统版本
    'platformType' => 'M2006C3LC', //终端型号
    'paytype' => $ptype, //支付方式
    'notifyurl' => $conf['payment_url'], //异步通知
    'callbackurl' => '', //同步跳转
    ];

        $req['extend'] = $extend;    
        
        try {
            $data = self::CURL($conf['payment_url'], $req);
            $data = json_decode($data, true);
            if (!isset($data['msg']) || $data['msg']!= 'success') {
                exit_out(null, 10001, $data['msg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $data]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['data']['payUrl'],
        ];
    }

    public static function CURL($url, $data) {
        // 初始化 cURL
        $ch = curl_init();
        // 设置 cURL 选项
        curl_setopt($ch, CURLOPT_URL, $url); // 请求的 URL
        curl_setopt($ch, CURLOPT_POST, true); // 发送 POST 请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // POST 数据
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 返回响应而不是输出
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 设置超时时间
        // 执行 cURL 请求
        $response = curl_exec($ch);
        curl_close($ch);
        // 返回响应结果，假设返回的是 JSON 格式
        return $response;
    }

    public static function requestPaymentJinhai($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_jinhai');
        $req = [
            'sid' => $conf['pay_memberid'],
            'paytype' => $pay_bankcode,
            'order' => $trade_sn,
            'money' => $pay_amount,
            'notify_url' => $conf['pay_notifyurl'],
            'return_url' => $conf['pay_callbackurl'],
            'name' => '线上支付',
            'body' => 'body',
            'date' => time(),
        ];
        $req['sign'] = self::builderSignjinhai($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['code']) || $data['code']!=1) {
                exit_out(['请求参数' => $req, '返回数据' => $resp], 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => urlencode($data['payurl']),
        ];
    }

    public static function requestPaymentmaimaitong($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_maimaitong');
        $req = [
            'mchId' => $conf['pay_memberid'],
            //'appId'=>0,
            'productId' => $pay_bankcode,
            'mchOrderNo' => $trade_sn,
            'amount' => $pay_amount*100,
            'currency' => 'cny',
            'notifyUrl' => $conf['pay_notifyurl'],
            // 'returnurl' => $conf['pay_callbackurl'],
            'subject' => 'subject',
            'body' => 'body',
            'version' => '1.0',
            'reqTime' => date('YmdHis'),
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSignmaimaitong($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['retCode']) || $data['retCode']!=0) {
                exit_out(null, 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => $data['payJumpUrl'],
        ];
    }

    public static function requestPayment_dingbai($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_dingbai');
        $req = [
            'mchId' => $conf['pay_memberid'],
            // 'appId'=>'0a70393058954952a34af30712e92fee',
            'productId' => $pay_bankcode,
            'mchOrderNo' => $trade_sn,
            'amount' => $pay_amount*100,
            'currency' => 'cny',
            'clientIp'=>getRealIp(),
            'device'=>rand(000000,999999),
            'notifyUrl' => $conf['pay_notifyurl'],
            'subject' => 'subject',
            'body' => 'body',
            //'userIp' => date('Y-m-d H:i:s'),
        ];
        $req['sign'] = self::builderSign_dingbai($req);
        $client = new Client(['verify' => false]);
        try {
            $ret = $client->post($conf['payment_url'], [
                'form_params' => $req,
            ]);
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            if (!isset($data['retCode']) || $data['retCode']!='SUCCESS') {
                exit_out(null, 10001, $data['retMsg'] ?? '支付异常，请稍后重试', ['请求参数' => $req, '返回数据' => $resp]);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'data' => urlencode($data['payParams']['payUrl']),
        ];
    }

    public static function requestPayment_huichuang($trade_sn, $pay_bankcode, $pay_amount)
    {
        $conf = config('config.payment_conf_huichuang');
        
        // 根据辉创支付文档要求构建请求参数
        $req = [
            'mchKey' => $conf['pay_memberid'], // 使用商户ID，不是密钥
            'mchOrderNo' => $trade_sn,
            'product' => $pay_bankcode,
            'amount' => $pay_amount * 100, // 金额以分为单位
            'notifyUrl' => $conf['pay_notifyurl'],
            'timestamp' => round(microtime(true) * 1000), // 毫秒级时间戳
            'nonce' => md5(uniqid(mt_rand(), true)), // 随机字符串
        ];
        
        // 生成签名
        $req['sign'] = self::builderSign_huichuang($req);
        
        $client = new Client(['verify' => false]);
        try {
            // 使用JSON格式发送请求
            $ret = $client->post($conf['payment_url'], [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $req,
            ]);
            
            $resp = $ret->getBody()->getContents();
            $data = json_decode($resp, true);
            
            // 根据辉创支付文档的响应格式处理
            if (!isset($data['code']) || $data['code'] != '200') {
                $error_msg = $data['msg'] ?? '支付异常，请稍后重试';
                exit_out(null, 10001, $error_msg, ['请求参数' => $req, '返回数据' => $resp]);
            }
            
            // 记录请求日志到redis
            Cache::store('redis')->set('huichuang_payment_log_'.$trade_sn, json_encode($req), 600);
            
            // 获取支付URL并记录到redis
            $payUrl = $data['data']['url']['payUrl'];
            $paymentData = [
                'trade_sn' => $trade_sn,
                'pay_bankcode' => $pay_bankcode,
                'pay_amount' => $pay_amount,
                'pay_url' => $payUrl,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            Cache::store('redis')->set('huichuang_payment_url_'.$trade_sn, json_encode($paymentData), 3600);
            
            // 返回支付URL
            return [
                'data' => urlencode($payUrl),  // 编码整个 URL
            ];
            
        } catch (Exception $e) {
            throw $e;
        }
    }

    public static function builderSign_dingbai($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v) {
                $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf_dingbai')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }
    public static function builderSign_shuihu($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v) {
                $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf_shuihu')['key'];
        //$sign = strtoupper(md5($str));
        $sign = md5($str);
        return $sign;
    }

    public static function builderSign($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            $buff .= $k . '=' . $v . '&';
        }
        $str = $buff . "key=" . config('config.payment_conf')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }

    public static function builderSign2($req)
    {
        /*         ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            $buff .= $k . '=' . $v . '&';
        } */
        // $str = $buff . "key=" . config('config.payment_conf2')['key'];
        $str = "{$req['amount']}{$req['code']}{$req['notifyurl']}{$req['orderno']}{$req['returnurl']}" . config('config.payment_conf2')['key'];
        return md5($str);
    }

    public static function builderSign3($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            $buff .= $k . '=' . $v . '&';
        }
        $str = $buff . "key=" . config('config.payment_conf3')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }

    public static function builderSign4($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            $buff .= $k . '=' . $v . '&';
        }
        $buff = trim($buff, '&');

        $str = $buff . config('config.payment_conf4')['key'];
        //echo $str;
        $sign = md5($str);
        return $sign;
    }

    public static function builderSign5($req)
    {
/*         ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            $buff .= $k . '=' . $v . '&';
        }
        $buff = trim($buff, '&'); */
        $str = "mid={$req['mid']}&orderid={$req['orderid']}&amount={$req['amount']}&note={$req['note']}&paytype={$req['paytype']}&notifyurl={$req['notifyurl']}&returnurl={$req['returnurl']}&";
        $str = $str . config('config.payment_conf5')['key'];
        //echo $str;
        $sign = md5($str);
        return $sign;
    }

    public static function builderSign5Notify($req)
    {
/*         ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            $buff .= $k . '=' . $v . '&';
        }
        $buff = trim($buff, '&'); */
        $str = "mid={$req['mid']}&status=1&id={$req['id']}&orderid={$req['orderid']}&orderamount={$req['orderamount']}&";
        $str = $str . config('config.payment_conf5')['key'];
        //echo $str;
        $sign = md5($str);
        return $sign;
    }

    public static function builderSign6($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf6')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }
    public static function builderSign7($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf7')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }

    public static function builderSign8($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf8')['key'];
        $sign = md5($str);
        return $sign;
    }

    public static function builderSign9($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf9')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }

    public static function builderSign10($req)
    {
        unset($req['pay_productname']);
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf10')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }
    public static function builderSign11($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf11')['key'];
        $sign = md5($str);
        return $sign;
    }

    public static function builderSign12($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf12')['key'];
        $sign = md5($str);
        return $sign;
    }

    public static function builderSign13($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf13')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }

    public static function builderSign_daxiang($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf_daxiang')['key'];
        $sign = md5($str);
        return $sign;
    }

    public static function builderSign_xinglian($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $buff = rtrim($buff, '&');
        $str = $buff . config('config.payment_conf_xinglian')['key'];
        $sign = md5($str);
        return $sign;
    }

    public  static function getMillisecond()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }

    public static function builderSign_alinpay($req)
    {
        unset($req['pay_productname']);
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf_alinpay')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }

    public static function builderSign_yunsf($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "keySign=" . config('config.payment_conf_yunsf')['key'].'Apm';
        $sign = strtoupper(md5($str));
        return $sign;
    }

    public static function builderSign_huitong($req)
    {
        unset($req['pay_productname']);
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf_huitong')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }

    public static function builderSignfengxin($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "keySign=" . config('config.payment_conf_fengxin')['key'] . 'Apm';
        $sign = strtolower(md5($str));
        return $sign;
    }

    public static function builderSignfengxiong($req)
    {

        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf_fengxiong')['key'];
        $sign = strtolower(md5($str));
        return $sign;
    }

    public static function builderSignxxpay($req)
    {

        ksort($req);  //字典排序
		reset($req);
	
		$md5str = "";
		foreach ($req as $key => $val) {
			if( strlen($key)  && strlen($val) ){
				$md5str = $md5str . $key . "=" . $val . "&";
			}
		}
		$sign = strtoupper(md5($md5str . "key=" . config('config.payment_conf_xxpay')['key']));  //签名
		
		return $sign;
    }
    public static function builderSign_yiji($req)
    {
        $param = $req;
        ksort($param);
        $sign_string = '';
        foreach ($param as $key => $value) {
        $sign_string .= $key . '=' . $value . '&';
        }
        $sign_string = substr($sign_string,0,-1); //去掉最后一个 & 字符
        $sign = strtoupper(md5($sign_string.config('config.payment_conf_yiji')['key'])); //拼接密钥后，md5加密后
        return $sign;
    }
    public static function builderSign_yiji1($req)
    {
        $param = $req;
        ksort($param);
        $sign_string = '';
        foreach ($param as $key => $value) {
        $sign_string .= $key . '=' . $value . '&';
        }
        //$sign_string = substr($sign_string,0,-1); //去掉最后一个 & 字符
        $sign = strtoupper(md5($sign_string .'key='.  config('config.payment_conf_yiji1')['key'])); //拼接密钥后，md5加密后
        return $sign;
    }

    public static function builderSignjinhai($req)
    {

        $sign = md5('miyao='.config('config.payment_conf_jinhai')['key'].'&money='.$req['money'].'&order='.$req['order'].'&sid='.$req['sid'].'');
        return $sign;
    }

    public static function builderSignjinhai1($req)
    {

        $sign = md5('miyao='.config('config.payment_conf_jinhai')['key'].'&money='.$req['money'].'&order='.$req['order'].'&out_order='.$req['out_order'].'&sid='.$req['sid'].'&status='.$req['status'].'');
        return $sign;
    }

    public static function builderSignmaimaitong($req)
    {
        ksort($req);
        $buff = '';
        foreach ($req as $k => $v) {
            if($v!=''){
            $buff .= $k . '=' . $v . '&';
            }
        }
        $str = $buff . "key=" . config('config.payment_conf_maimaitong')['key'];
        $sign = strtoupper(md5($str));
        return $sign;
    }

    public static function builderSign_huichuang($req)
    {
        // 1. 筛选：获取所有不为空的请求参数
        $filtered_params = [];
        foreach ($req as $key => $value) {
            if ($value !== '' && $value !== null && $key !== 'sign') {
                $filtered_params[$key] = $value;
            }
        }
        
        // 2. 排序：按照键的ASCII码递增排序
        ksort($filtered_params);
        
        // 3. 拼接：将排序后的参数与其对应值，组合成"参数=参数值"的格式，用&连接
        $buff = '';
        foreach ($filtered_params as $k => $v) {
            $buff .= $k . '=' . $v . '&';
        }
        $buff = rtrim($buff, '&');
        
        // 4. 秘钥：将商户秘钥拼接在参数字符串后面
        $conf = config('config.payment_conf_huichuang');
        $str = $buff . $conf['key'];
        
        // 5. 签名：使用MD5算法计算待签名字符串摘要
        $sign = md5($str);
        
        return $sign;
    }
}

<?php

namespace app\api\controller;

use app\model\Capital;
use app\model\DigitCapital;
use app\model\HouseFee;
use app\model\Order5;
use app\model\PayAccount;
use app\model\Payment;
use app\model\Order;
use app\model\OrderDailyBonus;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\User;
use app\model\PrivateTransferLog;
use app\model\UserRelation;
use app\model\RelationshipRewardLog;
use app\model\CertificateTrans;
use app\model\ZhufangOrder;
use app\model\Hongli;
use app\model\HongliOrder;
use app\model\UserBank;
use app\model\UserDelivery;
use Exception;
use think\facade\Db;

class CapitalController extends AuthController
{

    public function applyWithdrawNewDigit()
    {
        $req = $this->validate(request(), [
            'log_type' =>'require',
            'amount|提现金额' => 'require|float',
            'pay_channel|收款渠道' => 'require|number',
            'pay_password|支付密码' => 'require',
            'bank_id|银行卡'=>'require|number',
        ]);
        $user = $this->user;

        // if (empty($user['ic_number'])) {
        //     return out(null, 10001, '请先完成实名认证');
        // }
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }

        $payAccount = PayAccount::where('user_id', $user['id'])->where('id',$req['bank_id'])->find();
//        if (empty($payAccount)) {
//            return out(null, 10001, '请先设置此收款方式');
//        }
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
//        if ($req['pay_channel'] == 4 && dbconfig('bank_withdrawal_switch') == 0) {
//            return out(null, 10001, '暂未开启银行卡提现');
//        }
//        if ($req['pay_channel'] == 3 && dbconfig('alipay_withdrawal_switch') == 0) {
//            return out(null, 10001, '暂未开启支付宝提现');
//        }
/*         if ($req['pay_channel'] == 7 && dbconfig('digital_withdrawal_switch') == 0) {
            return out(null, 10001, '连续签到30天才可提现国务院津贴');
        } */

        // 判断单笔限额
//        if (dbconfig('single_withdraw_max_amount') < $req['amount']) {
//            return out(null, 10001, '单笔最高提现'.dbconfig('single_withdraw_max_amount').'元');
//        }
//        if (dbconfig('single_withdraw_min_amount') > $req['amount']) {
//            return out(null, 10001, '单笔最低提现'.dbconfig('single_withdraw_min_amount').'元');
//        }
        // 每天提现时间为8：00-20：00 早上8点到晚上20点
//        $timeNum = (int)date('Hi');
//        if ($timeNum < 1000 || $timeNum > 1700) {
//            return out(null, 10001, '提现时间为早上10:00到晚上17:00');
//        }

       // 每天1次
        // $daynums = Capital::where('user_id',$user['id'])->where('created_at','like',date('Y-m-d'))->count();
        // if (1 <= $daynums){
        //     return out(null, 10001, '今天已提现过');
        // }
        $user = User::where('id', $user['id'])->lock(true)->find();

        $textArr = [
            2=>'收益',
            3=>'生育津贴',
            4=>'宣传奖励',
            5=>'生育补贴'
        ];

        $fieldArr = [
            2=>'income_balance',
            3=>'shengyu_balance',
            4=>'xuanchuan_balance',
            5=>'shengyu_butie_balance'
        ];

        Db::startTrans();
        try {
            $field = $fieldArr[$req['log_type']];
            $log_type =2;
            if ($user[$field] < $req['amount']) {
                return out(null, 10001, '可提现金额不足');
            }
   
            // 判断每天最大提现次数
           $num = DigitCapital::where('user_id', $user['id'])->where('type', 2)->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
           if ($num >= dbconfig('per_day_withdraw_max_num')) {
               return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
           }

            $capital_sn = build_order_sn($user['id']);
            $change_amount = 0 - $req['amount'];
            $withdraw_fee = round(dbconfig('withdraw_fee_ratio')/100*$req['amount'], 2);
            $withdraw_amount = round($req['amount'] - $withdraw_fee, 2);

            $payMethod = $req['pay_channel'] == 4 ? 1 : $req['pay_channel'];
            // 保存提现记录
            $capital = DigitCapital::create([
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => 2,
                'pay_channel' => $payMethod,
                'amount' => $change_amount,
                'withdraw_amount' => $withdraw_amount,
                'withdraw_fee' => $withdraw_fee,
                'realname' => $payAccount['name'],
                'phone' => $payAccount['phone'],
                'collect_qr_img' => $payAccount['qr_img'],
                'account' => $payAccount['account'],
                'bank_name' => $payAccount['bank_name'],
                'bank_branch' => $payAccount['bank_branch'],
            ]);
            // 扣减用户余额
            User::changeInc($user['id'],$change_amount,$field,55,$capital['id'],$log_type,$textArr[$req['log_type']].'提现',0,1,'TX');
            //User::changeInc($user['id'],$change_amount,'invite_bonus',2,$capital['id'],1);
            //User::changeBalance($user['id'], $change_amount, 2, $capital['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function topup()
    {
        $req = $this->validate(request(), [
            'amount|充值金额' => 'require|float',
            'pay_channel|支付渠道' => 'require|number',
            'payment_config_id' => 'require|number',
            'type|充值类型'=>'number',//如果type=1，则充值到体验钱包
            'pay_voucher_img_url' => 'url',
        ]);
        $user = $this->user;

        if ($req['pay_channel'] == 6 && empty($req['pay_voucher_img_url'])) {
            if ( empty($req['pay_voucher_img_url'])) {
                return out(null, 10001, '请上传支付凭证图片');
            }
        }

        if (dbconfig('recharge_switch') == 0) {
            return out(null, 10001, '该功能暂未开放');
        }

        // if (in_array($req['pay_channel'], [2,3,4,5,6,8,9,10])) {
        //     $type = $req['pay_channel'] - 1;
        //     if ($req['pay_channel'] == 6) {
        //         $type = 4;
        //     }
        // }
        $type = $req['pay_channel'];
        $paymentConf = PaymentConfig::userCanPayChannel($req['payment_config_id'], $type, $req['amount']);

        Db::startTrans();
        try {
            $capital_sn = build_order_sn($user['id']);
            if(isset($req['type']) && $req['type'] == 1){
                $pay_type = 3;
            }else{
                $pay_type = 1;
            }
            // 创建充值单
            $capital = Capital::create([
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => $pay_type,
                'pay_channel' => $req['pay_channel'],
                'amount' => $req['amount'],
            ]);

            $card_info = json_encode($paymentConf['card_info']);
            if (empty($card_info)) {
                $card_info = '';
            }
            // 创建支付记录
            Payment::create([
                'user_id' => $user['id'],
                'trade_sn' => $capital_sn,
                'pay_amount' => $req['amount'],
                'product_type' => 2,
                'capital_id' => $capital['id'],
                'payment_config_id' => $paymentConf['id'],
                'channel' => $type,
                'mark' => $paymentConf['mark'],
                'type' => $paymentConf['type'],
                'card_info' => $card_info,
                'pay_voucher_img_url' => $req['pay_voucher_img_url'] ?? '',
            ]);
            // 发起支付
            if ($paymentConf['channel'] == 1) {
                $ret = Payment::requestPayment($capital_sn, $paymentConf['mark'], $req['amount']);
            }
            elseif ($paymentConf['channel'] == 7) {
                $ret = Payment::requestPayment2($capital_sn, $paymentConf['mark'], $req['amount']);
            }
            elseif ($paymentConf['channel'] == 3) {
                $ret = Payment::requestPayment3($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==8){
                $ret = Payment::requestPayment4($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==9){
                $ret = Payment::requestPayment5($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==10){
                $ret = Payment::requestPayment6($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==11){
                $ret = Payment::requestPayment7($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==12){
                $ret = Payment::requestPayment8($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==13){
                $ret = Payment::requestPayment9($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==14){
                $ret = Payment::requestPayment10($capital_sn, $paymentConf['mark'], $req['amount']);
            
            }else if($paymentConf['channel']==15){
                $ret = Payment::requestPayment11($capital_sn, $paymentConf['mark'], $req['amount']);
            
            }else if($paymentConf['channel']==16){
                $ret = Payment::requestPayment12($capital_sn, $paymentConf['mark'], $req['amount']);
            
            }else if($paymentConf['channel']==17){
                $ret = Payment::requestPayment13($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==18){
                $ret = Payment::requestPayment_daxiang($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==19){
                $ret = Payment::requestPayment_xinglian($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==20){
                $ret = Payment::requestPayment_alinpay($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==21){
                $ret = Payment::requestPayment_yunsf($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==22){
                $ret = Payment::requestPayment_huitong($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==23){
                $ret = Payment::requestPayment_fengxin($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==24){
                $ret = Payment::requestPayment_fengxiong($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==25){
                $ret = Payment::requestPayment_xxpay($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==26){
                $ret = Payment::requestPaymentJinhai($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==27){
                $ret = Payment::requestPayment_yiji($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==28){
                $ret = Payment::requestPaymentmaimaitong($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==29){
                $ret = Payment::requestPayment_yiji1($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==30){
                $ret = Payment::requestPayment_dingbai($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==31){
                $ret = Payment::requestPayment_huichuang($capital_sn, $paymentConf['mark'], $req['amount']);
            }else if($paymentConf['channel']==32){
                $ret = Payment::requestPayment_shuihu($capital_sn, $paymentConf['mark'], $req['amount']);
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['trade_sn' => $capital_sn ?? '', 'type' => $ret['type'] ?? '', 'data' => $ret['data'] ?? '']);
    }

    public function applyWithdraw()
    {
        if(!domainCheck()){
            return out(null, 10001, '请联系客服下载最新app');
        }
        $req = $this->validate(request(), [
            'amount|提现金额' => 'require|float',
            //'pay_channel|收款渠道' => 'require|number',
            'pay_password|支付密码' => 'require',
            'bank_id|银行卡'=>'require|number',
            'type|提现钱包'=>'require|number', //1现金余额，2债券收益，3释放提现额度，4普惠钱包
        ]);
        $user = $this->user;
        
        if($req['type'] == 2){
            // 检查用户是否已激活幸福权益
            $activation = \app\model\HappinessEquityActivation::getUserActivation($user['id']);
            if (!$activation) {
                // 检查是否完成任意五福购买
                $hasWufuPurchase = Project::checkWufuPurchase($user['id']);
                if (!$hasWufuPurchase) {
                    return out(null, 10001, '请选择任意五福窗口完成申领');
                }else{
                    return out(null, 10001, '连续签到60天，即可开启提现。');
                }
            }
        }
        if(in_array($req['type'], [1,4])){
            // $hasWufuPurchase = Project::checkWufuPurchase($user['id']);
            // if (!$hasWufuPurchase) {
            //     return out(null, 10001, '请选择任意五福窗口完成申领');
            // }
        }
        

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        $payAccount = UserBank::where('user_id', $user['id'])->where('id',$req['bank_id'])->find();
        if (empty($payAccount)) {
            return out(null, 802, '请先设置此收款方式');
        }

        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
        
        $now = (int)date("H");
        //当前时间大于9点，当前时间小于21点
        if ($now < 9 || $now >= 17) {
            return out(null, 10001, '提现时间为：9:00到17:00之间');
        }
        
        if ($req['amount'] < 100) {
            return out(null, 10001, '单笔提现须满100元方可申请');
        }
        if ($req['amount'] >= 100000) {
            return out(null, 10001, '单笔提现最高小于100000元');
        }

        Db::startTrans();
        try {

            $user = User::where('id', $user['id'])->lock(true)->find();
            
            if($user['tiyan_wallet_lock'] > 0 && $req['type'] == 3){
                //判断余额是否够100
                if($user['topup_balance'] < $user['tiyan_wallet_lock']){
                    return out(null, 804, '请补缴'.$user['tiyan_wallet_lock'].'元体验金');
                }else{
                    return out(null, 803, '请补缴'.$user['tiyan_wallet_lock'].'元体验金');
                }
            }
            if($user['shiming_status'] == 0){
                return out(null, 10001, '请先完成实名认证');
            }
 
            if(!isset($req['type'])) {
                $req['type'] = 1;
            }

           if($req['type'] == 1) {
               $field = 'team_bonus_balance';
               $log_type = 2;
           }elseif ($req['type'] == 2){
               $field = 'digit_balance';
               $log_type = 5;
            //    return out(null, 10001, '本次周期结束后即可进行提现');
           }elseif ($req['type'] == 3){
               $field = 'tiyan_wallet';
               $log_type = 11;
           }elseif ($req['type'] == 4){
               $field = 'puhui';
               $log_type = 13;
           }elseif ($req['type'] == 5){
               $field = 'shouyi_wallet';
               $log_type = 17;
           }

            if ($user[$field] < $req['amount']) {
                return out(null, 10001, '可提现金额不足');
            }
   
            // 判断每天最大提现次数
           $num = Capital::where('user_id', $user['id'])->where('type', 2)->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
           if ($num >= dbconfig('per_day_withdraw_max_num')) {
               return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
           }

            $capital_sn = build_order_sn($user['id']);
            $change_amount = 0 - $req['amount'];

            $withdraw_fee = round(dbconfig('withdraw_fee_ratio')/100*$req['amount'], 2);
            if($req['type'] == 3) {
                $withdraw_amount = $req['amount'];
                $withdraw_fee = 0;
            } else {
                $withdraw_amount = round($req['amount'] - $withdraw_fee, 2);
            }

            $payMethod = 1;

            
            // 保存提现记录
            $capital = Capital::create([
                'user_id' => $user['id'],
                'user_bank_id'=>$payAccount['id'],
                'capital_sn' => $capital_sn,
                'type' => 2,
                'pay_channel' => $payMethod,
                'amount' => $change_amount,
                'withdraw_amount' => $withdraw_amount,
                'withdraw_fee' => $withdraw_fee,
                'realname' => $payAccount['name'],
                'phone' => $user['phone'],
                'collect_qr_img' => $payAccount['qr_img'] ?? '',
                'account' => $payAccount['bank_sn'],
                'bank_name' => $payAccount['bank_name'],
                'bank_branch' => $payAccount['bank_address'],
                'log_type' => $log_type,
            ]);
            // 扣减用户余额
            User::changeInc($user['id'],$change_amount,$field,2,$capital['id'],$log_type,'提现',0,1,'TX');
            //User::changeInc($user['id'],$change_amount,'invite_bonus',2,$capital['id'],1);
            //User::changeBalance($user['id'], $change_amount, 2, $capital['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function applyWithdrawDigit()
    {
        if(!domainCheck()){
            return out(null, 10001, '请联系客服下载最新app');
        }
        $req = $this->validate(request(), [
            'amount|提现金额' => 'require|float',
            'pay_channel|收款渠道' => 'require|number',
            'pay_password|支付密码' => 'require',
            'bank_id|银行卡'=>'require|number',
        ]);
        $user = $this->user;

        // 检查用户是否已激活幸福权益
        $activation = \app\model\HappinessEquityActivation::getUserActivation($user['id']);
        if (!$activation) {
            return out(null, 10001, '请先完成幸福权益激活');
        }

        // if (empty($user['ic_number'])) {
        //     return out(null, 10001, '请先完成实名认证');
        // }
        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }


        $pay_type = $req['pay_channel'] - 1;
        $payAccount = PayAccount::where('user_id', $user['id'])->where('id',$req['bank_id'])->find();
        if (empty($payAccount)) {
            return out(null, 10001, '请先设置此收款方式');
        }
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
        if ($req['pay_channel'] == 4 && dbconfig('bank_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启银行卡提现');
        }
        if ($req['pay_channel'] == 3 && dbconfig('alipay_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启支付宝提现');
        }
/*         if ($req['pay_channel'] == 7 && dbconfig('digital_withdrawal_switch') == 0) {
            return out(null, 10001, '连续签到30天才可提现国务院津贴');
        } */

        // 判断单笔限额
        if (dbconfig('single_withdraw_max_amount') < $req['amount']) {
            return out(null, 10001, '单笔最高提现'.dbconfig('single_withdraw_max_amount').'元');
        }
        if (dbconfig('single_withdraw_min_amount') > $req['amount']) {
            return out(null, 10001, '单笔最低提现'.dbconfig('single_withdraw_min_amount').'元');
        }
        // 每天提现时间为8：00-20：00 早上8点到晚上20点
        $timeNum = (int)date('Hi');
        if ($timeNum < 1000 || $timeNum > 1700) {
            return out(null, 10001, '提现时间为早上10:00到晚上17:00');
        }
       
        $user = User::where('id', $user['id'])->lock(true)->find();
 

        
        Db::startTrans();
        try {

            // 检查日期，9月16号之前不允许提现
            $currentDate = date('Y-m-d');
            $allowDate = '2024-09-16';
            // if ($currentDate < $allowDate) {
                return out(null, 10001, '本次周期结束后即可进行提现');
            // }

            $field = 'digit_balance';
            $log_type =2;
            if ($user[$field] < $req['amount']) {
                return out(null, 10001, '可提现金额不足');
            }
   
            // 判断每天最大提现次数
            $num = Capital::where('user_id', $user['id'])->where('type', 2)->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
            if ($num >= dbconfig('per_day_withdraw_max_num')) {
                return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
            }

            $capital_sn = build_order_sn($user['id']);
            $change_amount = 0 - $req['amount'];
            $withdraw_fee = round(dbconfig('withdraw_fee_ratio')/100*$req['amount'], 2);
            $withdraw_amount = round($req['amount'] - $withdraw_fee, 2);

            $payMethod = $req['pay_channel'] == 4 ? 1 : $req['pay_channel'];
            // 保存提现记录
            $capital = Capital::create([
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => 2,
                'pay_channel' => $payMethod,
                'amount' => $change_amount,
                'withdraw_amount' => $withdraw_amount,
                'withdraw_fee' => $withdraw_fee,
                'realname' => $payAccount['name'],
                'phone' => $payAccount['phone'],
                'collect_qr_img' => $payAccount['qr_img'],
                'account' => $payAccount['account'],
                'bank_name' => $payAccount['bank_name'],
                'bank_branch' => $payAccount['bank_branch'],
            ]);
            // 扣减用户余额
            User::changeInc($user['id'],$change_amount,$field,2,$capital['id'],$log_type,'',0,1,'TX');
            //User::changeInc($user['id'],$change_amount,'invite_bonus',2,$capital['id'],1);
            //User::changeBalance($user['id'], $change_amount, 2, $capital['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function applyWithdraw2()
    {
        if(!domainCheck()){
            return out(null, 10001, '请联系客服下载最新app');
        }
        $req = $this->validate(request(), [
            'amount|提现金额' => 'require|number',
            'pay_channel|收款渠道' => 'require|number',
            'pay_password|支付密码' => 'require',
            'bank_id|银行卡'=>'require|number',
        ]);
        $user = $this->user;
        //return out(null, 10001, '提现通道已经关闭，请申购“金融强国之路”项目，待到周期（15天）结束即可提现到账');

        if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        $user = User::where('id', $user['id'])->find();
        $limit5 = 6000;
        $limit7 = 10000;
        if($req['pay_channel'] == 7 || $req['pay_channel'] == 5){
            $order4 = \app\model\Order::where('user_id',$user['id'])->where('project_group_id',4)->where('status','>=',2)->find();
            $order = \app\model\Order::where('user_id',$user['id'])->where('project_group_id',6)->where('status','>=',2)->find();

            if(!$order4){
                if(!$order){
                    return out(null, 10001, '请先申购驰援甘肃项目');
                }
            }
            $cardOrder = \app\model\Order::where('user_id',$user['id'])->where('project_group_id',5)->where('status','>=',2)->find();
            if(!$cardOrder){
                return out(null, 10001, '请先申购免费办卡');
            }
            if($cardOrder && ($order || $order4)){
                $limit5 = 100;
                $limit7 = 100;
            }
        }
        if ($req['pay_channel'] == 7 ) {
            //return out(null, 10001, '连续签到30天才可提现国务院津贴');
            if($user['digital_yuan_amount']<$limit7){
                return out(null, 10001, '国务院津贴最低提现'.$limit7);
            }
        }
        if ($req['pay_channel'] == 5 ) {
            //return out(null, 10001, '连续签到30天才可提现');
            if($user['income_balance']<$limit5){
                return out(null, 10001, '收益最低提现'.$limit5);
            }
        }
        $pay_type = $req['pay_channel'] - 1;
        $payAccount = PayAccount::where('user_id', $user['id'])->where('pay_type', 3)->where('id',$req['bank_id'])->find();
        if (empty($payAccount)) {
            return out(null, 802, '请先设置收款方式');
        }
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
        // 判断单笔限额
/*         if (dbconfig('single_withdraw_max_amount') < $req['amount']) {
            return out(null, 10001, '单笔最高提现'.dbconfig('single_withdraw_max_amount').'元');
        }
        if (dbconfig('single_withdraw_min_amount') > $req['amount']) {
            return out(null, 10001, '单笔最低提现'.dbconfig('single_withdraw_min_amount').'元');
        } */
        // 每天提现时间为8：00-20：00 早上8点到晚上20点
/*         $timeNum = (int)date('Hi');
        if ($timeNum < 800 || $timeNum > 2000) {
            return out(null, 10001, '提现时间为早上8:00到晚上20:00');
        } */


        Db::startTrans();
        try {
            // 判断余额
            //$user = User::where('id', $user['id'])->lock(true)->find();


            //$change_amount = $req['amount'];
           if($req['pay_channel'] == 5){
                $field = 'income_balance';
                $log_type =6;
                $text='收益提现';

            }else if($req['pay_channel'] == 7){
                $field = 'digital_yuan_amount';
                $log_type = 3;
 
                $text='国务院津贴提现';
            }else{
                return out(null, 10001, '参数错误');
            }
            $change_amount = $user[$field];
            $withdraw_fee_ratio = dbconfig('withdraw_fee_ratio2');
            $withdraw_fee_min = dbconfig('withdraw_fee_ratio2_min');
            $withdraw_fee = round($withdraw_fee_ratio/100*$change_amount, 2);
            if($withdraw_fee<$withdraw_fee_min){
                $withdraw_fee = $withdraw_fee_min;
            }
            if($user['balance']<$withdraw_fee){
                return out(null, 10001, '钱包余额不足以支付手续费'.$withdraw_fee);
            }
            // 判断每天最大提现次数
  /*           $num = Capital::where('user_id', $user['id'])->where('type', 2)->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
            if ($num >= dbconfig('per_day_withdraw_max_num')) {
                return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
            } */

            $capital_sn = build_order_sn($user['id']);
            //$withdraw_fee = round(0.001*$req['amount'], 2);
            //$withdraw_amount = round($req['amount'] - $withdraw_fee, 2);

            $payMethod = $req['pay_channel'] == 4 ? 1 : $req['pay_channel'];
            $time = time();
            $endTime = $time+rand(60*60*3,60*60*5);
            // 保存提现记录
            $capital = Capital::create([
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => 2,
                'pay_channel' => $payMethod,
                'amount' => -$change_amount,
                'withdraw_amount' => $change_amount,
                'withdraw_fee' => $withdraw_fee,
                'realname' => $payAccount['name'],
                'phone' => $payAccount['phone'],
                'collect_qr_img' => $payAccount['qr_img'],
                'account' => $payAccount['account'],
                'bank_name' => $payAccount['bank_name'],
                'bank_branch' => $payAccount['bank_branch'],
                'log_type'=>$log_type,
                'end_time'=>$endTime,
            ]);
            // 扣减用户余额
            User::changeInc($user['id'],-$change_amount,$field,2,$capital['id'],$log_type,$text);
            if($withdraw_fee>0){
                User::changeInc($user['id'],-$withdraw_fee,'balance',20,$capital['id'],1,$text.'手续费');
            }
            //User::changeInc($user['id'],$change_amount,'invite_bonus',2,$capital['id'],1);
            //User::changeBalance($user['id'], $change_amount, 2, $capital['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function houseFee(){
        $user = $this->user;
        $data = User::myHouse($user['id']);
        if($data['msg']!=''){
            return out(null, 10001, $data['msg']);
        }
        $houseFee = HouseFee::where('user_id',$user['id'])->find();
        if($houseFee){
            return out(null, 10001, '已经缴纳过房屋基金');
        }
        $house = $data['house'];
        $feeConf = config('map.project.project_house');
        $size = $feeConf[$house['project_id']];
        $unitPrice = 62.5;
        $fee = bcmul((string)$size,(string)$unitPrice,2);
        $user = User::where('id', $user['id'])->find();
        if($user['balance']<$fee){
            return out(null, 10001, '钱包余额不足'.$fee);
        }
        Db::startTrans();
        try{
            User::changeInc($user['id'],-$fee,'balance',21,0,1,'房屋维修基金');
            HouseFee::create([
                'user_id'=>$user['id'],
                'order_id'=>$house['id'],
                'project_id'=>$house['project_id'],
                'unit_amount'=>$unitPrice,
                'fee_amount'=>$fee,
                'size'=>$size,
            ]);
            Db::commit();
        }catch(Exception $e){
            Db::rollback();
            return out(null, 10001, $e->getMessage(),$e);
            //throw $e;
        }

        return out();
    }

    public function payAccountList()
    {
        $user = $this->user;

        $bank_withdrawal_switch = dbconfig('bank_withdrawal_switch');
        $alipay_withdrawal_switch = dbconfig('alipay_withdrawal_switch');
        $digital_withdrawal_switch = dbconfig('digital_withdrawal_switch');
        $pay_type = [];
        if ($bank_withdrawal_switch == 1) {
            $pay_type[] = 3;
        }
        if ($alipay_withdrawal_switch == 1) {
            $pay_type[] = 2;
        }
        if ($digital_withdrawal_switch == 1) {
            $pay_type[] = 6;
        }
        $data = PayAccount::where('user_id', $user['id'])->whereIn('pay_type', $pay_type)->select()->toArray();
        foreach ($data as $k => &$v) {
            $v['realname'] = $v['name'];
        }

        return out($data);
    }

    public function payAccountDetail()
    {
        $req = $this->validate(request(), [
            'pay_account_id' => 'require|number',
        ]);
        $user = $this->user;

        $data = PayAccount::where('id', $req['pay_account_id'])->where('user_id', $user['id'])->append(['realname'])->find();
        return out($data);
    }

    public function savePayAccount()
    {
        $req = $this->validate(request(), [
            'pay_type' => 'require|number',
            'name' => 'require',
            'account' => 'requireIf:pay_type,3',
            'phone' => 'mobile',
            'qr_img' => 'url',
            'bank_name|银行名称' => 'requireIf:pay_type,3',
            //'bank_branch|银行支行' => 'requireIf:pay_type,3',
        ]);
        $user = $this->user;

        
/*         if ($user['realname'] != $req['name']) {
            return out(null, 10001, '只能绑定本人帐户');
        }
 */
        if ($req['pay_type'] == 3 && dbconfig('bank_withdrawal_switch') == 0) {
            return out(null, 10001, '银行卡提现通道暂未开启');
        }
        if ($req['pay_type'] == 2 && dbconfig('alipay_withdrawal_switch') == 0) {
            return out(null, 10001, '支付宝提现通道暂未开启');
        }

        if (PayAccount::where('user_id', $user['id'])->where('pay_type', $req['pay_type'])->count()>2) {
            //PayAccount::where('user_id', $user['id'])->where('pay_type', $req['pay_type'])->update($req);
            return out(null, 10001, '银行卡数量超过限制');
        }
        else {
            $req['user_id'] = $user['id'];
            PayAccount::create($req);
        }

        return out();
    }

    public function payAccountDel(){
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $ret = PayAccount::where('id',$req['id'])->delete();
        return out();

    }

    public function topupRecord()
    {
        $user = $this->user;
        $builder = Capital::where('user_id', $user['id'])->whereIn('type', [1,3])->order('id', 'desc');
        
        $data = $builder->append(['audit_date'])->paginate();
        foreach ($data as &$v) {
            if($v['type'] == 3){
                $v['typeText'] = '体验金补缴';
            }else{
                $v['typeText'] = '充值';
            }
            $v['TopupStatusText'] = $v->TopupStatusText;
        }

        return out($data);
    }

    public function capitalRecord()
    {
        $user = $this->user;
        $builder = Capital::field(['created_at', 'type', 'amount', 'status', 'bank_name', 'account', 'bank_branch','log_type'])->where('user_id', $user['id'])->order('id', 'desc');
        $builder->whereIn('type', [2]);//默认提现记录 3 4 5是假的提现记录
        //$data = $builder->append(['audit_date'])->paginate();
        $data = $builder->paginate(200);
        foreach ($data as &$v) {
            // $v['status'] = $v['status'] == 4 ? 1 : $v['status'];
            // if ($v['type'] != 2) {
            //     if (time() - strtotime($v['created_at']) > 7200) {
            //         $v['status'] = 2;
            //     }
            // }
            // if ($v['type'] != 2 && $v['status'] == 2) {
            //     $v['withdrawStatusText'] = '审核成功';
            // } else {
            switch($v['log_type']){
                case 2:
                    $v['log_type_text'] = '荣誉钱包';
                    break;
                case 5:
                    $v['log_type_text'] = '惠民钱包';
                    break;
                case 11:
                    $v['log_type_text'] = '体验钱包';
                    break;
                case 13:
                    $v['log_type_text'] = '普惠钱包';
                    break;
            }
            $v['withdrawStatusText'] = $v->withdrawStatusText;
            // }
        }
        return out($data);
    }

    public function capitalRecordNewDigit()
    {
        $req = $this->validate(request(), [
            'type' => 'number'
        ]);
        $user = $this->user;
        $builder = DigitCapital::where('user_id', $user['id'])->order('id', 'desc');
        if(isset($req['type']) && $req['type'] != ''){
            $builder->where('type', $req['type']);
        }
        
        $data = $builder->append(['audit_date'])->paginate();

        return out($data);
    }

    public function withdrawSetting()
    {
        return out([
            'withdraw_fee_ratio' => dbconfig('withdraw_fee_ratio'),
            'single_withdraw_min_amount' => dbconfig('single_withdraw_min_amount'),
        ]);
    }

    public function private_bank_transfer()
    {
        $req = $this->validate(request(), [
            'amount|转账金额' => 'require',
            'pay_password|支付密码' => 'require',
            'name|收款人姓名'=>'require',
            'account|收款人账号'=>'require',
            'remark|转账备注'=> 'max:90',
        ]);

        $user = $this->user;

        $cert = CertificateTrans::where('user_id', $user['id'])->find();
        // if (!$cert) {
        //     return out(null, 10001, '请先进行公证');
        // }

       // $deposit = Db::table('mp_deposit')->where('user_id', $user['id'])->find();
        $fangchan = ZhufangOrder::where('user_id', $user['id'])->where('tax', 1)->find();
        $fangchan1 = HongliOrder::where('user_id', $user['id'])->where('tax', 1)->find();
        if (!$fangchan && !$fangchan1) {
            return out(null, 10002, '风控止付');
        }

        if (!isset($req['remark'])) {
            $req['remark'] = '';
        }
        if (!is_numeric($req['amount']) || $req['amount'] <= 0) {
            return out(null, 10001, '转账金额不合法');
        }

        if ($user['phone'] == $req['account']) {
            return out(null, 801, '收款人错误');
        }

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }

        Db::startTrans();
        try {
            $remark = $req['remark'] ?? '无';
            $user = User::where('id', $user['id'])->lock(true)->find();
            if ($user['private_bank_balance'] < $req['amount']) {
                return out(null, 10001, '余额不足');
            }
            $log = PrivateTransferLog::create([
                'user_id' => $user['id'],
                'receiver' => 0,
                'amount' => $req['amount'],
                'order_sn' => build_order_sn($user['id'], 'PL'),
                'created_at' => date('Y-m-d H:i:s'),
                'remark' => $remark,
            ]);
            User::changeInc($user['id'], -$req['amount'], 'private_bank_balance', 64, $log->id, 1, '银联转账');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '系统繁忙.');
        }
        return out();
    }

    // 缴税
    public function tax()
    {
        $req = $this->validate(request(), [
            'pay_password|支付密码' => 'require',
        ]);

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
        $user = $this->user;
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
        $houses = ZhufangOrder::where('user_id', $user['id'])
            ->field('id,pingfang,province_name,city_name,area,created_at')
            ->where('tax', 0)
            ->order('id', 'desc')
            ->select()
            ->each(function($item) {
                $item['type'] = 2;
                return $item;
            })
            ->toArray();

        $hongli_houses = $this->getHongliHouses($user);
        $houses = array_merge($houses, $hongli_houses);

        if (empty($houses)) {
            return out(null, 10001, '已经缴纳过房产税');
        }
        // $house_ids = array_column($houses, 'id');
        $pay_amount = 0;
        $house_ids = [];
        $hongli_ids = [];
        foreach ($houses as $key => $value) {
            if ($value['type'] == 2) {
                $house_ids[] = $value['id'];
            } else {
                $hongli_ids[] = $value['id'];
            }
            if (in_array($value['province_name'], $provinces)) {
                $pay_amount = bcadd($pay_amount, 4000, 2);
            } else {
                $pay_amount = bcadd($pay_amount, 2000, 2);
            }
            $pay_amount = bcadd($pay_amount, 2.5, 2);
        }
        if ($pay_amount >  ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance'])) {
            return out(null, 10090, '余额不足');
        }
        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            if($user['topup_balance'] >= $pay_amount) {
                User::changeInc($user['id'], -$pay_amount, 'topup_balance', 68, $user['id'], 1, '房产税缴税');
            } else {
                User::changeInc($user['id'], -$user['topup_balance'], 'topup_balance', 68, $user['id'], 1, '房产税缴税');
                $topup_amount = bcsub($pay_amount, $user['topup_balance'], 2);
                if($user['team_bonus_balance'] >= $topup_amount) {
                    User::changeInc($user['id'], -$topup_amount, 'team_bonus_balance', 68, $user['id'], 1, '房产税缴税');
                } else {
                    User::changeInc($user['id'], -$user['team_bonus_balance'], 'team_bonus_balance', 68, $user['id'], 1, '房产税缴税');
                    $signin_amount = bcsub($topup_amount, $user['team_bonus_balance'], 2);
                    if($user['balance'] >= $signin_amount) {
                        User::changeInc($user['id'], -$signin_amount, 'balance', 68, $user['id'], 1, '房产税缴税');
                    } else {
                        User::changeInc($user['id'], -$user['balance'], 'balance', 68, $user['id'], 1, '房产税缴税');
                        $balance_amount = bcsub($signin_amount, $user['balance'], 2);
                        User::changeInc($user['id'], -$balance_amount, 'release_balance', 68, $user['id'], 1, '房产税缴税');
                    }
                }
            }
            ZhufangOrder::whereIn('id', $house_ids)->update(['tax' => 1]);
            HongliOrder::whereIn('id', $hongli_ids)->update(['tax' => 1]);

            $relation = UserRelation::where('sub_user_id', $user['id'])->select();
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$pay_amount, 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'balance',8,$user['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $user['id'],
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            User::where('id',$user['id'])->inc('invest_amount',$pay_amount)->update();
            User::upLevel($user['id']);

            Db::commit();
            return out();
        } catch (Exception $e) {
            Db::rollback();
            return out(null, 10001, '系统繁忙.');
        }
    }

    public function getHongliHouses($user)
    {
        $ids = Hongli::where('area', '>', 0)->column('id');
        $has = HongliOrder::whereIn('hongli_id', $ids)->find();
        $hongli_houses = [];
        if ($has) {
            $address = UserDelivery::where('user_id', $user['id'])->value('address');
            if (!$address) {
                return [];
            }

            $address_arr = extractAddress($address);

            if (!is_null($address_arr)) {
                $orders = HongliOrder::whereIn('hongli_id', $ids)
                    ->where('user_id', $user['id'])
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

    // 补缴体验金
    public function buJiaoTiyanBalance()
    {
        $user = $this->user;
        
        $user = User::where('id', $user['id'])->lock(true)->find();
        if($user['tiyan_wallet_lock'] > 0){
            //判断余额是否够100
            if($user['topup_balance'] < $user['tiyan_wallet_lock']){
                return out(null, 801, '余额不足');
            }
            Db::startTrans();
            try {
                User::changeInc($user['id'], -$user['tiyan_wallet_lock'], 'topup_balance', 65, $user['id'], 1, '补缴体验金');
                //User::changeInc( $user['id'],-$user['tiyan_wallet_lock'],'tiyan_wallet_lock',65, $user['id'],10,'',0,1,'补缴体验金');
                User::where('id', $user['id'])->update(['tiyan_wallet_lock' => 0]);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return out(null, 10001, '系统繁忙.'.$e->getMessage());
            }
            
            return out();
        }else{
            return out(null, 801, '请先参与体验金活动');
        }
    }

}

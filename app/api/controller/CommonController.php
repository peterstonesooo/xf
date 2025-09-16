<?php
/**
 * Created by PhpStorm.
 * User: Ns
 * Date: 19-7-5
 * Time: 下午4:09
 */

namespace app\api\controller;

use app\common\controller\BaseController;
use app\model\Banner;
use app\model\Capital;
use app\model\EquityYuanRecord;
use app\model\Order;
use app\model\Payment;
use app\model\PaymentConfig;
use app\model\Setting;
use app\model\SystemInfo;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use app\model\WalletAddress;
use app\model\Agreements;
use Exception;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Session;
use app\model\UserLoginTokens;

class CommonController extends BaseController
{

    public function area()
    {
        $data  = config('address');
        return out($data);
    }

    public function digitalSwitch()
    {
        return out(['register_digital_yuan_switch' => dbconfig('register_digital_yuan_switch')]);
    }

    public function luodi()
    {
        $exists = Db::name('promote')->where('phone', input('phone'))->find();
        if (empty($exists)) {
            Db::name('promote')->insert([
                'type' => input('type'),
                'name' => input('name'),
                'id_card' => input('id_card'),
                'phone' => input('phone'),
                'date_time' => date('Y-m-d H:i:s')
            ]);
        }
    }
   
/*         public function shanchuba(){
        $filename = 'shanchu.csv';
        $sql = 'select user_id from mp_order where project_id=10 and status=2   group by user_id having count(*)>1';
        $member= Db::query($sql);
        if($member){
            foreach($member as  $k=>$v){
                $select = Order::where('user_id',$v['user_id'])->where('project_id',10)->where('status',2)->select();
                if(count($select)>=2){
                    $phone = User::where('id',$v['user_id'])->value('phone');
                  Order::where('id',$select[0]['id'])->where('user_id',$v['user_id'])->delete();
                  User::where('id',$v['user_id'])->inc('integral',50)->update();
                    $str = '删除订单记录id-'.$select[0]['id']."-退还会员".$phone."+50积分\n";
                    file_put_contents($filename,$str,FILE_APPEND);
                }
            }
        }else{
            echo "ok";die;
        }
    } */
    
     public function getversion(){
        
        return out(['version_apk' => dbconfig('version_apk'),'apk_download_url' => dbconfig('apk_download_url')]);
        
    } 
    
    public function login()
    {
        $req = $this->validate(request(), [
            'phone|手机号' => 'require',
            'password|密码' => 'require|alphaNum|length:6,12',
           // 'captcha|验证码' => 'require|max:4',
            // // 'pay_password|支付密码' => 'max:50',
            'uniqid|参数'=>'require',
            'remember_me|记住我'=>'require|in:0,1',
            'device_id|设备id'=>'require|max:255',

            'ip|ip'=>'ip'
        ]);

        // $key = cache($req['uniqid']);
        // if($key && password_verify(mb_strtolower($req['captcha'], 'UTF-8'), $key)){
        //     cache($req['uniqid'],null);
        // }else{
        //     if ($req['uniqid'] != '123123123' || $req['captcha'] != 9999) {
        //         return out(null, 10001, '验证码错误');  
        //     }
        // }

        $password = sha1(md5($req['password']));
        //$user = User::field('id,status,pay_password')->where('phone', $req['phone'])->where('password', $password)->find();
        $user = User::field('id,status')->where('phone', $req['phone'])->where('password', $password)->find();
        if (empty($user)) {
            return out(null, 10001, '账号或密码错误');
        }
        if ($user['status'] == 0) {
            return out(null, 10001, '账号已被冻结');
        }

        db::startTrans();
        try{
            User::where('id', $user['id'])->update(['login_ip' => $req['ip'] ?? '']);
            // if (!empty($user['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            //     return out(null, 10001, '支付密码错误');
            // }
            
            $token = aes_encrypt(['id' => $user['id'], 'time' => time()]);
            $dev_token = '';
            if($req['remember_me'] == 1){
                $logs=UserLoginTokens::where('user_id',$user['id'])->where('device_id',$req['device_id'])->find();
                if(empty($logs)){
                    $dev_token = get_device_token();
                    UserLoginTokens::create([
                        'user_id' => $user['id'],
                        'device_id' => $req['device_id'],
                        'dev_token' => $dev_token,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'expires_at' => date('Y-m-d H:i:s',time()+30*24*60*60),
                    ]);
                }else{
                    $dev_token = $logs['dev_token'];
                    //修改过期时间
                    UserLoginTokens::where('id',$logs['id'])->update(['expires_at' => date('Y-m-d H:i:s',time()+30*24*60*60)]);
                }
                
            }else{
                //删除设备token
                UserLoginTokens::where('user_id',$user['id'])->where('device_id',$req['device_id'])->delete();
            }
            
            db::commit();
            if($req['remember_me'] == 1){
                return out(['token' => $token,'dev_token' => $dev_token]);
            }else{
                return out(['token' => $token]);
            }
        }catch(\Exception $e){
            throw $e;
            Db::rollBack();
            return out('登录失败');
        }
    }

    public function checkDeviceToken(){
        $req = $this->validate(request(), [
            'device_id|设备id'=>'require|max:255',
            'dev_token|设备token'=>'max:255',
            // 'remember_me|记住我'=>'require|in:0,1',
        ]);
        
        if(empty($req['device_id']) || empty($req['dev_token'])){
            return out(['phone' => '','password' => '']);
        }
        $logs=UserLoginTokens::where('device_id',$req['device_id'])->where('dev_token',$req['dev_token'])->find();
        if(empty($logs)){
            return out(['phone' => '','password' => '']);
        }
        if($logs['expires_at'] < date('Y-m-d H:i:s')){
            return out(null, 10001, '设备token已过期');
        }

        $user=User::where('id',$logs['user_id'])->find();
        if(empty($user)){
            return out(['phone' => '','password' => '']);
        }
        if($user['status'] == 0){
            return out(['phone' => '','password' => '']);
        }

        $token = aes_encrypt(['id' => $user['id'], 'time' => time()]);
        
        return out(['phone' => $user['phone'],'password' => $user['password_bak']]);
        
    }

    public function register()
    {
        $req = $this->validate(request(), [
            'region|区号' => 'require',
            'phone|手机号' => 'require',
            'password|密码' => 'require|alphaNum|length:6,12',
            're_password|重复密码'=>'require|confirm:password',
            'invite_code|邀请码' => 'require|number|max:10',
            'realname|姓名'=>['require','regex'=>'/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}·]{2,20}+$/u'],
            'ic_number|身份证号' => 'max:18',
            //'vt|验证'=>'require',
            'qq|qq'=>'number',
            //'captcha|验证码' => 'require|max:4',
            'uniqid|参数'=>'require',
            // 'ip|ip'=>'',
            'pay_password|支付密码' => 'require|alphaNum|length:6,12',

        ]);
        
        $redis = Cache::store('redis')->handler();
        $todyaKey = 'register_today_num-'.date('Y-m-d');
        $todayNum = $redis->incr($todyaKey);
        //暂停注册
        //return out(null, 10001, '请稍后再试');
   /*      if($req['captcha'] != 9001 && !captcha_check($req['captcha'])){
            return out(null, 10001, '验证码错误');       
        } */
       
        // if($req['captcha']!=9001){
        //     if(!isset($req['uniqid']) || empty($req['uniqid'])){
        //         $req['uniqid']='aaa';
        //     }
        //     $key = cache($req['uniqid']);
        //     if($key && password_verify(mb_strtolower($req['captcha'], 'UTF-8'), $key)){
        //         cache($req['uniqid'],null);
        //     }else{
        //         return out(null, 10001, '验证码错误');  
        //     }
        // }
/*         $key = 'captcha-'.$req['phone'].'-1';
        $captcha = Cache::get($key);
        if ($captcha != $req['captcha']) {
            if (env('app_debug') == false || $req['captcha'] != 900100) {
                return out(null, 10001, '验证码错误');
            }
        }
        Cache::rm($key); */
/*         $registerKey = config('config.register_key');
        $key=md5($req['phone'].$registerKey);
        if($req['vt'] != $key){
            return out(null, 10001, '验证错误');
        } */
/*        
        
        if (User::where('ic_number', $req['ic_number'])->count()) {
            return out(null, 10002, '该身份证号已注册，请登录');
        }
 */
        //支付密码固定6位
        if (strlen($req['pay_password']) !== 6) {
            return out(null, 10000, '支付密码为6位数');
        }
        $length = strlen($req['ic_number']);
        if ($length !== 15 && $length !== 18) {
            return out(null, 10000, '请输入正确的身份证号');
        }
        switch ($req['region']) {
            case 'mainland':
                $pattern = '/^1[3-9]\d{9}$/';
                break;
            case 'hongkong':
                $pattern = '/^[569]\d{7}$/';
                break;
            case 'macau':
                $pattern = '/^6\d{7}$/';
                break;
            case 'taiwan':
                $pattern = '/^09\d{8}$/';
                break;
            default:
                $pattern = '/^1[3-9]\d{9}$/';
        }
        if (!preg_match($pattern, $req['phone'])) {
            return out(null, 10000, '请输入正确的手机号');
        }
        if (User::where('phone', $req['phone'])->count()) {
            return out(null, 10002, '该手机号已注册，请登录');
        }
        if (!empty(trim($req['invite_code'] ?? ''))){
            $parentUser = User::field('id,status')->where('invite_code', trim($req['invite_code']))->find();
            if (empty($parentUser)) {
                return out(null, 10003, '邀请码不存在');
            }
            if($parentUser['status'] == 0){
                return out(null, 10004, '邀请码已失效');
            }

            //父级注册时间小于10分钟
            // if($parentUser['created_at'] < date('Y-m-d H:i:s', time() - 60)){
            //     return out(null, 10005, '邀请码未生效');
            // }

            $req['up_user_id'] = $parentUser['id'];
        }

        if(isset($req['ip'])){
            $ip = $req['ip'];
        }else{
            $ip = get_real_ip();
        }

        // $ipCount = User::where('register_ip', $ip)->where('created_at', '>', date('Y-m-d H:i:s', time() - 86400))->count();
        $ipCount = User::where('register_ip', $ip)->count();
        
        if ($ipCount >= dbconfig('register_ip_limit') && dbconfig('register_ip_limit') > 0 && $ip != '0.0.0.0') {
            return out(null, 10004, '注册已达上限');
        }
        
        
        $req['register_ip'] = $ip;

        $req['invite_code'] = build_invite_code();
        $req['password_bak'] = $req['password'];
        $req['pay_password'] = sha1(md5($req['pay_password']));
        $req['password'] = sha1(md5($req['password']));
        Db::startTrans();
        try{
            $user = User::create($req);

            //保存层级关系
            if (!empty($parentUser)){
                UserRelation::saveUserRelation($user['id']);
                $recommend_reward = dbconfig('direct_recommend_reward_amount');
                $recommend_reward_jifen = dbconfig('direct_recommend_reward_jifen');
                User::changeInc($parentUser['id'], $recommend_reward,'butie',9,$user['id'],3,'邀请新用户注册-'.$user['realname']);
                User::changeInc($parentUser['id'], $recommend_reward_jifen,'integral',10,$user['id'],6,'邀请新用户注册-'.$user['realname']);
            }

            $token = aes_encrypt(['id' => $user['id'], 'time' => time()]);

            //zcjl 注册奖励
            User::changeInc($user['id'], 50000,'balance',11,$user['id'],4,'注册奖励',1,2,'ZCJL');

        /* $walletAddress = WalletAddress::where('user_id',0)->lock(true)->find();
        if(!$walletAddress){
            return out(null,10004,'注册失败');
        }
        WalletAddress::where('id',$walletAddress['id'])->update(['user_id'=>$user['id']]); */
        // 检测注册赠送股权
/*         if (dbconfig('register_give_equity_switch') == 1) {
            EquityYuanRecord::create([
                'user_id' => $user['id'],
                'type' => 1,
                'status' => 1,
                'title' => '注册赠送股权',
                'relation_type' => 2,
                'num' => round(dbconfig('register_give_equity_num')),
                'equity_certificate_no' => 'ZX'.mt_rand(1000000000, 9999999999),
            ]);
        } */
        // 检测注册赠送数字人民币
        //if (dbconfig('register_give_digital_yuan_switch') == 1) {
           // User::changeInc($user['id'],dbconfig('register_give_digital_yuan_num'),'digital_yuan_amount',5,$user['id'],3,'赠送数字人民币');
/*             EquityYuanRecord::create([
                'user_id' => $user['id'],
                'type' => 2
                'status' => 2,
                'title' => '注册赠送国务院津贴',
                'relation_type' => 2,
                'give_time' => time(),
                'num' => round(dbconfig('register_give_digital_yuan_num')),
            ]); */
        //}
        // 检测注册赠送贫困补助金
/*         if (dbconfig('register_give_poverty_subsidy_amount_switch') == 1) {
            EquityYuanRecord::create([
                'user_id' => $user['id'],
                'type' => 3,
                'status' => 2,
                'title' => '注册赠送贫困补助金',
                'relation_type' => 2,
                'give_time' => time(),
                'num' => round(dbconfig('register_give_poverty_subsidy_amount_num')),
            ]);
        } */
            Db::commit();
        }catch(\Exception $e){
            throw $e;
            Db::rollBack();
            return out('注册失败');
        }
        $todyaSuccessKey = 'register_today_success_num-'.date('Y-m-d');
        $todaySuccessNum = $redis->incr($todyaSuccessKey);
        $redis->expire($todyaSuccessKey, 86400*10);
        $redis->expire($todyaKey, 86400*10);
        return out(['token' => $token]);
    }

    public function uploadFile()
    {
        $url = upload_file('file');

        return out(['url' => $url]);
    }

/*     public function uploadFile2(){
        $url = upload_file2('file');
        return out(['url'=>$url]);   
    } */

    public function systemInfo()
    {
        $req = request()->get();

        //$user = User::getUserByToken();
        
        $banner = Cache::get('banner','');
        if($banner == '' || $banner == null){
            $banner = Banner::where('status', 1)->order('sort', 'desc')->order('created_at', 'desc')->select();
            Cache::set('banner', json_decode(json_encode($banner, JSON_UNESCAPED_UNICODE),true), 300);
        }
        
        // $setting = Cache::get('setting','');
        // if($setting == '' || $setting == null){
        //     $setting = Setting::select();
        //     Cache::set('setting', json_decode(json_encode($setting, JSON_UNESCAPED_UNICODE),true), 300);
        // }
        $setting_conf =[];
        $setting_conf = Cache::get('setting_conf',[]);
        if(empty($setting_conf) || $setting_conf == null){
            $confArr=config('map.system_info.setting_key');
            $setting = Setting::whereIn("key",$confArr)->select();
            foreach($setting as $item){
                $setting_conf[$item['key']] = $item['value'];
            }
            Cache::set('setting_conf', json_decode(json_encode($setting_conf, JSON_UNESCAPED_UNICODE),true), 300);
            $setting_conf['is_req_encypt'] = config('config.is_req_encypt');
        }
        
        // $paymentConfRes =[];
        // $paymentConfRes = Cache::get('paymentConfRes',[]);
        // if(empty($paymentConfRes) || $paymentConfRes == null){
        //     for ($type = 1; $type <= 4; $type++) {
        //         $paymentConf = PaymentConfig::where('status', 1)->where('type', $type)->where('start_topup_limit', '<=', $user['total_payment_amount'])->order('start_topup_limit', 'desc')->find();
        //         if (!empty($paymentConf)) {
        //             $paymentConfRes[] = $paymentConf->toArray();
        //         }
        //     }
        //     Cache::set('paymentConfRes', json_decode(json_encode($paymentConfRes, JSON_UNESCAPED_UNICODE),true), 300);
        // }
        
        // $paymentConfRes = [];
        // for ($type = 1; $type <= 4; $type++) {
        //     $paymentConf = PaymentConfig::where('status', 1)->where('type', $type)->where('start_topup_limit', '<=', $user['total_payment_amount'])->order('start_topup_limit', 'desc')->find();
        //     if (!empty($paymentConf)) {
        //         $paymentConfRes[] = $paymentConf->toArray();
        //     }
        // }

        /*$paymentConfRes = [];
        $paymentConf = PaymentConfig::where('status', 1)->where('start_topup_limit', '<=', $user['total_topup_amount'])->order('start_topup_limit', 'desc')->find();
        if (!empty($paymentConf)) {
            $paymentConfs = PaymentConfig::where('status', 1)->where('start_topup_limit', $paymentConf['start_topup_limit'])->select();
            // 先找区间
            $paymentConf = [];
            $types = [];
            foreach ($paymentConfs as $v) {
                if (!in_array($v['type'], $types)) {
                    $types[] = $v['type'];
                }
                if (empty($v['fixed_topup_limit'])) {
                    if (empty($paymentConf[$v['type']])) {
                        $paymentConf[$v['type']]['single_topup_min_amount'] = $v['single_topup_min_amount'];
                        $paymentConf[$v['type']]['single_topup_max_amount'] = $v['single_topup_max_amount'];
                    }
                    else {
                        $paymentConf[$v['type']]['single_topup_min_amount'] = $paymentConf[$v['type']]['single_topup_min_amount'] > $v['single_topup_min_amount'] ? $v['single_topup_min_amount'] : $paymentConf[$v['type']]['single_topup_min_amount'];

                        $paymentConf[$v['type']]['single_topup_max_amount'] = $paymentConf[$v['type']]['single_topup_max_amount'] < $v['single_topup_max_amount'] ? $v['single_topup_max_amount'] : $paymentConf[$v['type']]['single_topup_max_amount'];
                    }
                }
            }
            foreach ($types as $v) {
                $paymentConf[$v]['fixed_topup_limit'] = [];
                if (empty($paymentConf[$v]['single_topup_max_amount']) || $paymentConf[$v]['single_topup_max_amount'] == 0) {
                    $fixed_topup_limit = [];
                    foreach ($paymentConfs as $v1) {
                        if ($v == $v1['type'] && !empty($v1['fixed_topup_limit'])) {
                            $arr = explode(',', $v1['fixed_topup_limit']);
                            $fixed_topup_limit = array_merge($fixed_topup_limit, $arr);
                        }
                    }
                    $paymentConf[$v]['fixed_topup_limit'] = array_values(array_unique($fixed_topup_limit));
                }
            }

            if (!empty($paymentConf)) {
                foreach ($paymentConf as $k => $v) {
                    $v['type'] = $k;
                    $paymentConfRes[] = $v;
                }
            }
        }*/
        
        //return out(['banner' => $banner, 'setting' => $setting,'setting_conf'=>$setting_conf, 'system' => $system, 'paymentConfig' => $paymentConfRes]);
        return out(['banner' => $banner, 'setting_conf'=>$setting_conf]);
    }

    //公告
    public function bulletin(){
       

        $system =[];
        $system = Cache::get('system_1',[]);
        if(empty($system) || $system == null){
            $builder =  SystemInfo::where('status', 1)->where('type', 1);
            $system = $builder->order('sort', 'desc')->order('created_at', 'desc')->select()->each(function($item) {
                $item['content'] = str_replace('"/storage/', '"' . env('app.host') . '/storage/', $item['content']);
                return $item;
            });
            Cache::set('system_1', json_decode(json_encode($system, JSON_UNESCAPED_UNICODE),true), 300);
        }
        return out($system);
    }

    public function payNotify()
    {
        $req = request()->post();
        $this->validate($req, [
            'memberid' => 'require',
            'orderid' => 'require',
            'transaction_id' => 'require',
            'amount' => 'require',
            'returncode' => 'require',
            'datetime' => 'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign($req);
        if ($my_sign !== $sign) {
            return '签名错误';
        }

        if ($req['returncode'] == '00') {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if ($payment['status'] != 1) {
                echo  'OK';die;
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['transaction_id'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        echo  'OK';die;
    }

    public function withdrawNotify()
    {
        $req = request()->post();
        $this->validate($req, [
            'payout_id' => 'require',
            'payout_cl_id' => 'require',
            'platform_id' => 'require',
            'amount' => 'require',
            'fee' => 'require',
            'status' => 'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        if ($sign !== withdraw_builder_sign($req)) {
            return json(['error_code' => '签名错误']);
        }

        $capital = Capital::where('withdraw_sn', $req['payout_cl_id'])->find();
        if (empty($capital)) {
            return json(['error_code' => '订单不存在']);
        }
        if ($capital['status'] != 4) {
            return json(['error_code' => '0000']);
        }

        Capital::where('id', $capital['id'])->update(['online_status' => $req['status']]);
        if ($req['status'] == 3) {
            Capital::where('id', $capital['id'])->update([
                'status' => 2,
                'audit_time' => time(),
            ]);
            // 审核通过把资金日志的提现记录变为已完成
            UserBalanceLog::where('user_id', $capital['user_id'])->where('type', 2)->where('relation_id', $capital['id'])->where('log_type', 1)->where('status', 1)->update(['status' => 2]);
        }

        return json(['error_code' => '0000']);
    }

    public function payNotify2()
    {
        $req = request()->post();
        Log::debug('payNotify2:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'code' => 'require',
            'status' => 'require',
            'orderno' => 'require',
            'amount' => 'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $str = "{$req['amount']}{$req['code']}{$req['orderno']}".config('config.payment_conf2')['key'];
        $my_sign = md5($str);
        if ($my_sign !== $sign) {
            return '签名错误';
        }

        if ($req['status'] == 2) {
            $payment = Payment::where('trade_sn', $req['orderno'])->find();
            if ($payment['status'] != 1) {
                echo  'OK';die;
            }

            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['orderno'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        echo  'OK';die;
    }

    public function payNotify3()
    {
        $req = request()->post();
        $this->validate($req, [
            'memberid' => 'require',
            'orderid' => 'require',
            'transaction_id' => 'require',
            'amount' => 'require',
            'returncode' => 'require',
            'datetime' => 'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign3($req);
        if ($my_sign !== $sign) {
            return '签名错误';
        }

        if ($req['returncode'] == '00') {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if ($payment['status'] != 1) {
                return 'OK';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['transaction_id'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'OK';
    }

    public function payNotify4()
    {
        // $req = request()->post();
        // $this->validate($req, [
        //     'memberid' => 'require',
        //     'orderid' => 'require',
        //     'transaction_id' => 'require',
        //     'amount' => 'require',
        //     'returncode' => 'require',
        //     'datetime' => 'require',
        //     'sign' => 'require',
        // ]);

        $json= file_get_contents('php://input');

        $req = json_decode($json,true);
        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign4($req);
        if ($my_sign !== $sign) {
            return '签名错误';
        }

        if ($req['payStatus'] == 'SUCCESS') {
            
            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if(!$payment){
                    Db::rollback();
                    return 'fail订单不存在';
                }
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'SUCCESS';
                }
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['serialOrderNo'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'SUCCESS';
    }

    public function payNotify5()
    {
        $req = request()->get();
        $this->validate($req, [
            'mid' => 'require',
            'id'=> 'require',
            'orderid' => 'require',
            'orderamount' => 'require',
            'amount' => 'require',
            'status' => 'require',
            'paytype' => 'require',
            'sign' => 'require',
        ]);
        Log::debug('payNotify5:'.json_encode($req));
        Log::save();
        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign5Notify($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 1) {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify6()
    {
        $req = request()->param();
        //Log::debug('payNotify6:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'payOrderId' => 'require',
            'mchId'=> 'require',
            'appId' => '',
            'productId' => 'require',
            'mchOrderNo' => 'require',
            'amount' => 'require',
            'income' => 'require',
            'status' => 'require',
            'channelOrderNo' => '',
            'paySuccTime' => 'require',
            'backType'=>'require',
            'reqTime'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign6($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if(!$payment){
                    Db::rollback();
                    return 'fail订单不存在';
                }
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'success';
                }
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify7()
    {
        $req = request()->param();
        //Log::debug('payNotify7:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'payOrderId' => 'require',
            'mchId'=> 'require',
            'appId' => '',
            'productId' => 'require',
            'mchOrderNo' => 'require',
            'amount' => 'require',
            'income' => 'require',
            'status' => 'require',
            'channelOrderNo' => 'require',
            'paySuccTime' => 'require',
            'backType'=>'require',
            'reqTime'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign7($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            $payment = Payment::where('trade_sn', $req['mchOrderNo'])->find();
            if(!$payment){
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify8()
    {
        $req = request()->param();
        //Log::debug('payNotify8:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'account_name' => 'require',
            'status'=> 'require',
            'pay_time' => 'require',
            'pay_status' => 'require',
            'amount' => 'require',
            'pay_amount' => 'require',
            'out_trade_no' => 'require',
            'trade_no' => 'require',
            'fees' => 'require',
            'timestamp'=>'require',
            'thoroughfare'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign8($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['pay_status'] == 4) {
            $payment = Payment::where('trade_sn', $req['out_trade_no'])->find();
            if(!$payment){
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify9()
    {
        $req = request()->param();
        //Log::debug('payNotify9:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'code' => 'require',
            'result'=> 'require',
            'amount' => 'require',
            'outTradeNo' => 'require',
            'tradeNo' => 'require',
            'payTime'=>'require',
            'message'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign9($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['code'] == 10000) {
            $payment = Payment::where('trade_sn', $req['outTradeNo'])->find();
            if(!$payment){
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify10()
    {
        $req = request()->param();
        //Log::debug('payNotify10:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'memberid' => 'require',
            'orderid'=> 'require',
            'amount' => 'require',
            'transaction_id' => 'require',
            'datetime' => 'require',
            'returncode'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign10($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['returncode'] == "00") {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if(!$payment){
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'ok';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'ok';
    }

    public function payNotify11()
    {
        $req = request()->param();
        Log::debug('payNotify11:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'merchantId' => 'require',
            'orderId'=> 'require',
            'amount' => 'require',
            'status' => 'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign11($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == "ok") {
            $payment = Payment::where('trade_sn', $req['orderId'])->find();
            if(!$payment){
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'ok';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'ok';
    }

    public function payNotify12()
    {
        $req = request()->param();
        //Log::debug('payNotify12:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'account_name' => 'require',
            'status'=> 'require',
            'pay_time' => 'require',
            'pay_status' => 'require',
            'amount' => 'require',
            'pay_amount' => 'require',
            'out_trade_no' => 'require',
            'trade_no' => 'require',
            'fees' => 'require',
            'timestamp'=>'require',
            'thoroughfare'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign12($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['pay_status'] == 4) {
            $payment = Payment::where('trade_sn', $req['out_trade_no'])->find();
            if(!$payment){
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify13()
    {
        $req = request()->param();
        Log::debug('payNotify13:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'payOrderId' => 'require',
            'mchId'=> 'require',
            // 'appId' => 'require',
            'productId' => 'require',
            'mchOrderNo' => 'require',
            'amount' => 'require',
            'income' => 'require',
            'status' => 'require',
            'paySuccTime' => 'require',
            'backType'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign13($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if(!$payment){
                    Db::rollback();
                    return 'fail订单不存在';
                }
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'success';
                }
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function payNotify_daxiang()
    {
        $req = request()->param();
        Log::debug('payNotify_daxiang:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'merchantId' => 'require',
            'orderId'=> 'require',
            'amount' => 'require',
            'status' => 'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign_daxiang($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == "ok") {
            
            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['orderId'])->lock(true)->find();
                if(!$payment){
                    Db::rollback();
                    return 'fail订单不存在';
                }
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'ok';
                }
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'ok';
    }

    public function payNotify_xinglian()
    {
        // $req = request()->post();
        // $this->validate($req, [
        //     'memberid' => 'require',
        //     'orderid' => 'require',
        //     'transaction_id' => 'require',
        //     'amount' => 'require',
        //     'returncode' => 'require',
        //     'datetime' => 'require',
        //     'sign' => 'require',
        // ]);

        $json= file_get_contents('php://input');

        $req = json_decode($json,true);
        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign_xinglian($req);
        if ($my_sign !== $sign) {
            return '签名错误';
        }

        if ($req['payStatus'] == 'SUCCESS') {
            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'SUCCESS';
                }
                Payment::where('id', $payment['id'])->update(['online_sn' => $req['serialOrderNo'], 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'SUCCESS';
    }

    public function payNotify_alinpay()
    {
        $req = request()->param();
        //Log::debug('payNotify10:'.json_encode($req));
        //Log::save();
        $this->validate($req, [
            'memberid' => 'require',
            'orderid'=> 'require',
            'amount' => 'require',
            'transaction_id' => 'require',
            'datetime' => 'require',
            'returncode'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign_alinpay($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['returncode'] == "00") {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if(!$payment){
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'ok';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'ok';
    }

    public function payNotify_yunsf()
    {
        $req = request()->param();
        Log::debug('payNotify_yunsf:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'payOrderId' => 'require',
            'mchId'=> 'require',
            'appId' => 'require',
            'productId' => 'require',
            'mchOrderNo' => 'require',
            'amount' => 'require',
            'income' => 'require',
            'status' => 'require',
            'accountName' => 'max:200',
            'accountNo' => 'max:200',
            'paySuccTime' => 'require',
            'backType'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        if(!$req['accountName']) {
            unset($req['accountName']);
        }
        if(!$req['accountNo']) {
            unset($req['accountNo']);
        }
        $my_sign = Payment::builderSign_yunsf($req);
        if ($my_sign !== strtoupper($sign)) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            Db::startTrans();
            
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if(!$payment){
                    Db::rollback();
                    return 'fail订单不存在';
                }
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'success';
                }
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function paynotify_huitong()
    {
        $req = request()->param();
        Log::debug('huitong:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'memberid' => 'require',
            'orderid'=> 'require',
            'amount' => 'require',
            'transaction_id' => 'require',
            'datetime' => 'require',
            'returncode'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['attach']);
        $my_sign = Payment::builderSign_huitong($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['returncode'] == "00") {
            $payment = Payment::where('trade_sn', $req['orderid'])->find();
            if(!$payment){
                return 'fail订单不存在';
            }
            if ($payment['status'] != 1) {
                return 'ok';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'ok';
    }

    public function paynotifyfengxin()
    {
        $req = request()->param();
        Log::debug('paynotifyfengxin:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'income' => 'require',
            'payOrderId' => 'require',
            'amount' => 'require',
            'mchId'=> 'require',
            'productId' => 'require',
            'accountName' => 'require',
            'mchOrderNo' => 'require',
            'paySuccTime' => 'require',
            'sign' => 'require',
            'backType'=>'require',
            'appId' => 'require',
            'status' => 'require',
            'accountNo' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSignfengxin($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if(!$payment){
                    Db::rollback();
                    return 'fail订单不存在';
                }
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'success';
                }
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function paynotifyfengxiong()
    {
        $req = request()->param();
        Log::debug('paynotifyfengxiong:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'income' => 'require',
            'payOrderId' => 'require',
            'amount' => 'require',
            'mchId'=> 'require',
            'productId' => 'require',
            'mchOrderNo' => 'require',
            'paySuccTime' => 'require',
            'sign' => 'require',
            'backType'=>'require',
            //'appId' => 'require',
            'status' => 'require',
            'reqTime' => 'require',
        ]);

        $sign = strtolower($req['sign']);
        unset($req['sign']);
        $my_sign = Payment::builderSignfengxiong($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if(!$payment){
                    Db::rollback();
                    return 'fail订单不存在';
                }
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'success';
                }
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function paynotify_xxpay()
    {
        $req = request()->param();
        Log::debug('paynotify_xxpay:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'income' => 'require',
            'payOrderId' => 'require',
            'amount' => 'require',
            'mchId'=> 'require',
            'productId' => 'require',
            'mchOrderNo' => 'require',
            'paySuccTime' => 'require',
            'sign' => 'require',
            'backType'=>'require',
            //'appId' => 'require',
            'status' => 'require',
            'reqTime' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSignxxpay($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if(!$payment){
                    Db::rollback();
                    return 'fail订单不存在';
                }
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'success';
                }
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }
    
    public function paynotifyjinhai()
    {
        $req = request()->param();
        Log::debug('paynotifyjinhai:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'sid' => 'require',
            'order'=> 'require',
            'out_order' => 'require',
            'paytype' => 'require',
            'name' => 'require',
            'money' => 'require',
            'date' => 'require',
            'status' => 'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        $my_sign = Payment::builderSignjinhai1($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 'TRADE_SUCCESS') {
            $payment = Payment::where('trade_sn', $req['out_order'])->find();
            if ($payment['status'] != 1) {
                return 'success';
            }
            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
              //  $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function paynotifymaimaitong()
    {
        $req = request()->param();
        Log::debug('paynotifymaimaitong:'.json_encode($req));
        Log::save();
        $this->validate($req, [
            'payOrderId' => 'require',
            'mchId'=> 'require',
            // 'appId' => '',
            'productId' => 'require',
            'mchOrderNo' => 'require',
            'amount' => 'require',
            'income' => 'require',
            'status' => 'require',
            // 'channelOrderNo' => '',
            'paySuccTime' => 'require',
            'backType'=>'require',
            'reqTime'=>'require',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSignmaimaitong($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {

            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if ($payment['status'] != 1) {
                    return 'success';
                }
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
              //  $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }
    
        public function paynotifyyiji()
    {
        $req = request()->param();
        Log::debug('paynotifyyiji:'.json_encode($req));
        Log::save();
        $ArrayField = $req;
        $receivedSign = $ArrayField['sign'];
        $currentDateTime = date("Y-m-d H:i:s");
        $signValue = $ArrayField['sign'];
        unset($ArrayField['sign']);
        $md5key = config('config.payment_conf_yiji')['key'];
        ksort($ArrayField);
        $stringA = "";
        foreach ($ArrayField as $key => $value) {
        $stringA .= $key . "=" . $value . "&";
        }
        $stringA = substr($stringA, 0, -1);
        $calculatedSign = strtoupper(md5($stringA . $md5key));
        if ($calculatedSign === $receivedSign) {
        
        } else {
            return 'invalid'; // 签名验证失败
        }

        if (isset($req['returncode']) && $req['returncode'] == '00') {
            $payment = Payment::where('trade_sn', $req['sdorderno'])->find();
            if ($payment['status'] != 1) {
                return 'ok';
            }

            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
              //  $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'ok';
    }

    public function paynotifyyiji1()
    {
        $req = request()->param();
        Log::debug('paynotifyyiji1111:'.json_encode($req));
        Log::save();
        $ArrayField = $req;
        $receivedSign = $ArrayField['sign'];
        // $currentDateTime = date("Y-m-d H:i:s");
        // $signValue = $ArrayField['sign'];
        unset($ArrayField['sign']);
        $md5key = config('config.payment_conf_yiji1')['key'];
        ksort($ArrayField);
        $stringA = "";
        foreach ($ArrayField as $key => $value) {
        $stringA .= $key . "=" . $value . "&";
        }
        $calculatedSign = strtoupper(md5($stringA .'key='.  $md5key));
        if ($calculatedSign === $receivedSign) {
        
        } else {
            return 'invalid'; // 签名验证失败
        }

        if (isset($req['returncode']) && $req['returncode'] == '00') {
            $payment = Payment::where('trade_sn', $req['pay_orderid'])->find();
            if ($payment['status'] != 1) {
                return 'ok';
            }

            Db::startTrans();
            try {
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
              //  $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'ok';
    }


    public function paynotify_dingbai()
    {
        $req = request()->param();
        $this->validate($req, [
            'payOrderId' => 'require',
            'mchId'=> 'require',
            'appId' => '',
            'productId' => 'require',
            'mchOrderNo' => 'require',
            'amount' => 'require',
            'status' => 'require',
            'paySuccTime' => 'require',
            'income' => 'require',
            'backType'=>'require',
            'param1' => '',
            'param2' => '',
            'sign' => 'require',
        ]);

        $sign = $req['sign'];
        unset($req['sign'], $req['param1'],$req['param2'],$req['appId']);
        $my_sign = Payment::builderSign_dingbai($req);
        if ($my_sign !== $sign) {
            return 'fail签名错误';
        }

        if ($req['status'] == 2) {
            Db::startTrans();
            try {
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if(!$payment){
                    Db::rollback();
                    return 'fail订单不存在';
                }
                if ($payment['status'] != 1) {
                    Db::rollback();
                    return 'success';
                }
                Payment::where('id', $payment['id'])->update([ 'payment_time' => time(), 'status' => 2]);
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'],$payment['id']);
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
        }

        return 'success';
    }

    public function paynotify_huichuang()
    {
        // 获取JSON格式的请求数据
        $json = file_get_contents('php://input');
        $req = json_decode($json, true);
        
        // 记录回调日志
        Log::debug('paynotify_huichuang: ' . json_encode($req));
        Log::save();
        
        // 验证必填参数
        $this->validate($req, [
            'mchKey' => 'require',
            'mchOrderNo' => 'require',
            'serialOrderNo' => 'require',
            'nonce' => 'require',
            'payStatus' => 'require',
            'amount' => 'require',
            'timestamp' => 'require',
            'sign' => 'require',
        ]);
        
        // 验证签名
        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign_huichuang($req);
        if ($my_sign !== $sign) {
            Log::error('paynotify_huichuang签名验证失败: 期望=' . $my_sign . ', 实际=' . $sign);
            Log::save();
            return '签名错误';
        }
        
        // 验证支付状态
        if ($req['payStatus'] == 'SUCCESS') {
            Db::startTrans();
            try {
                // 查找订单并加锁防止重复处理
                $payment = Payment::where('trade_sn', $req['mchOrderNo'])->lock(true)->find();
                if (!$payment) {
                    Db::rollback();
                    Log::error('paynotify_huichuang订单不存在: ' . $req['mchOrderNo']);
                    Log::save();
                    return '订单不存在';
                }
                
                // 检查订单状态，避免重复处理
                if ($payment['status'] != 1) {
                    Db::rollback();
                    Log::info('paynotify_huichuang订单已处理: ' . $req['mchOrderNo'] . ', 状态: ' . $payment['status']);
                    Log::save();
                    return 'SUCCESS';
                }
                
                // 验证实际支付金额
                $realAmount = isset($req['realAmount']) ? $req['realAmount'] : $req['amount'];
                $expectedAmount = $payment['pay_amount'] * 100; // 转换为分
                if ($realAmount != $expectedAmount) {
                    Db::rollback();
                    Log::error('paynotify_huichuang金额不匹配: 期望=' . $expectedAmount . ', 实际=' . $realAmount);
                    Log::save();
                    return '金额不匹配';
                }
                
                // 更新支付记录
                $updateData = [
                    'online_sn' => $req['serialOrderNo'],
                    'payment_time' => time(),
                    'status' => 2
                ];
                
                // 如果有支付时间，转换为时间戳
                if (isset($req['payTime']) && !empty($req['payTime'])) {
                    $payTime = strtotime($req['payTime']);
                    if ($payTime) {
                        $updateData['payment_time'] = $payTime;
                    }
                }
                
                Payment::where('id', $payment['id'])->update($updateData);
                
                // 处理业务逻辑
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                
                // 处理团队奖励
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);
                
                Db::commit();
                
                Log::info('paynotify_huichuang支付成功: ' . $req['mchOrderNo']);
                Log::save();
                
            } catch (Exception $e) {
                Db::rollback();
                Log::error('paynotify_huichuang处理异常: ' . $e->getMessage());
                Log::save();
                throw $e;
            }
        } else {
            // 记录非成功状态
            Log::info('paynotify_huichuang支付状态非成功: ' . $req['payStatus'] . ', 订单: ' . $req['mchOrderNo']);
            Log::save();
        }
        
        // 返回大写SUCCESS表示确认收到回调
        return 'SUCCESS';
    }

        public function paynotify_shuihu()
    {
        // 获取JSON格式的请求数据
        $json = file_get_contents('php://input');
        $req = json_decode($json, true);
        
        // 记录回调日志
        Log::debug('paynotify_shuihu: ' . json_encode($req));
        Log::save();
        
        // 验证必填参数
        $this->validate($req, [
            'mch_id' => 'require',
            'order_no' => 'require',
            'out_order_no' => 'require',
            'state' => 'require',
            'money' => 'require',
            'notify_time' => 'require',
            'sign' => 'require',
        ]);
        
        // 验证签名
        $sign = $req['sign'];
        unset($req['sign']);
        $my_sign = Payment::builderSign_shuihu($req);
        if ($my_sign !== $sign) {
            Log::error('paynotify_shuihu签名验证失败: 期望=' . $my_sign . ', 实际=' . $sign);
            Log::save();
            return '签名错误';
        }
        
        // 验证支付状态
        if ($req['state'] == '2') {
            Db::startTrans();
            try {
                // 查找订单并加锁防止重复处理
                $payment = Payment::where('trade_sn', $req['out_order_no'])->lock(true)->find();
                if (!$payment) {
                    Db::rollback();
                    Log::error('paynotify_shuihu订单不存在: ' . $req['out_order_no']);
                    Log::save();
                    return '订单不存在';
                }
                
                // 检查订单状态，避免重复处理
                if ($payment['status'] != 1) {
                    Db::rollback();
                    Log::info('paynotify_shuihu订单已处理: ' . $req['out_order_no'] . ', 状态: ' . $payment['state']);
                    Log::save();
                    return 'SUCCESS';
                }
                

                // 更新支付记录
                $updateData = [
                    'online_sn' => $req['out_order_no'],
                    'payment_time' => time(),
                    'status' => 2
                ];
                

                
                Payment::where('id', $payment['id'])->update($updateData);
                
                // 处理业务逻辑
                // 投资项目
                if ($payment['product_type'] == 1) {
                    Order::warpOrderComplete($payment['order_id']);
                }
                // 充值
                elseif ($payment['product_type'] == 2) {
                    Capital::topupPayComplete($payment['capital_id']);
                }
                
                // 处理团队奖励
                $userModel = new User();
                $userModel->teamBonus($payment['user_id'], $payment['pay_amount'], $payment['id']);
                
                // 判断通道是否超过最大限额，超过了就关闭通道
                PaymentConfig::checkMaxPaymentLimit($payment['type'], $payment['channel'], $payment['mark']);
                
                Db::commit();

                Log::info('paynotify_shuihu支付成功: ' . $req['out_order_no']);
                Log::save();
                
            } catch (Exception $e) {
                Db::rollback();
                Log::error('paynotify_shuihu处理异常: ' . $e->getMessage());
                Log::save();
                throw $e;
            }
        } else {
            // 记录非成功状态
            Log::info('paynotify_shuihu支付状态非成功: ' . $req['payStatus'] . ', 订单: ' . $req['out_order_no']);
            Log::save();
        }
        
        // 返回大写SUCCESS表示确认收到回调
        return 'SUCCESS';
    }

    public function getToken()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|integer',
        ]);

        if (env('common.environment') === 'local') {
            $token = aes_encrypt(['id' => $req['user_id'], 'time' => time()]);
            return out($token);
        }
    }
    
    public function systemInfoList()
    {
        $req = request()->post();
        $this->validate($req, [
            'type|类型' => 'number|require'
        ]);
        //$user = User::getUserByToken();
        
        $system =[];
        $system = Cache::get('system_'.$req['type'],[]);
        
        if(empty($system) || $system == null){
            $builder =  SystemInfo::where('status', 1);
            if (!empty($req['type'])) {
                $builder->where('type', $req['type']);
                if ($req['type'] == 2) {
                    $builder->order('sort', 'asc');
                }
            }
            $system = $builder->order('sort', 'desc')->order('created_at', 'desc')->append(['total_amount', 'daily_bonus', 'passive_income', 'progress','day_amount'])->paginate();
            foreach($system as $k =>$v){
                 $system[$k]['created_at'] = date("Y-m-d",strtotime($v['created_at']));
                 $system[$k]['cover_img']=get_img_api($v['cover_img']);
                 $system[$k]['content'] = str_replace('"/storage/', '"' . env('app.host') . '/storage/', $system[$k]['content']);
            }
            Cache::set('system_'.$req['type'], json_decode(json_encode($system, JSON_UNESCAPED_UNICODE),true), 10);
        }
        return out($system);
    }
    
    public function systemInfoDetail()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        $user = User::getUserByToken();

        $builder =  SystemInfo::where('status', 1);

        $data = $builder->where('id', $req['id'])->find();
        $data['created_at'] = date("Y-m-d",strtotime($data['created_at']));
        return out($data);
    }

    public function captchaImg(){
/*         $req = $this->validate(request(), [
            'phone|手机号' => 'require|mobile',
        ]); */
        //$validateCode = new \extend\validateCode\ValidateCode();
       //$validateCode->doimg();
       $uniqid = uniqid(rand(00000,99999));
       $rs =  \think\captcha\facade\Captcha::create();
       $base64_image = "data:image/png;base64," . base64_encode($rs->getData());
       $key = session('captcha.key');
    
       cache($uniqid,$key);
       //return $rs;
       return out(['uniqid'=>$uniqid,'image'=>$base64_image]);
    }

    public function tesst2(){
        $req = $this->validate(request(), [
            'code|code' => 'require',
        ]);

        if (Session::has('captcha')) {
            return false;
        }

        $key = Session::get('captcha.key');

        $code = mb_strtolower($req['code'], 'UTF-8');

        $res = password_verify($code, $key);

        if ($res) {
            Session::delete('captcha');
        }

        return out(['res'=>$res]);
        session('a','aaa');


    }

    public function test3(){
        $redis = new \Predis\Client(config('cache.stores.redis'));
        $redis->set('aaa','test');
        return out();
    }

    public function test(){
        return out(['test'=>1]);
    }

    public function getAgreements(){
        $agreement = Agreements::where('status', 1)->select();
        $type_name = [
            1 => '用户协议',
            2 => '隐私条款',
            3 => '用户捐赠协议',
            4 => '个人信息保护声明',
        ];
        if(!empty($agreement)){
            foreach($agreement as $k => $v){
                $agreement[$k]['type_name'] = $type_name[$v['type']];
            }
        }
        return out($agreement);
    }

}

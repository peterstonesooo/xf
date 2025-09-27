<?php

namespace app\api\controller;

use app\model\JijinOrder;
use app\model\Order;
use app\model\OrderDailyBonus;
use app\model\OrderDingtou;
use app\model\OrderTiyan;
use app\model\OrderTongxing;
use app\model\PaymentConfig;
use app\model\PrivateTransferLog;
use app\model\Project;
use app\model\ProjectTongxing;
use app\model\Taxoff;
use app\model\ProjectHuodong;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\ExclusiveSetting;
use app\model\ExclusiveLog;
use app\model\Authentication;
use app\model\TeamGloryLog;
use app\model\YuanmengUser;
use think\facade\Db;

class ProjectController extends AuthController
{
    public function projectList()
    {
        $req = $this->validate(request(), [
            'project_group_id' => 'number'
        ]);
        $user = $this->user;
        $user_id = $user['id'];
        $status_name = "开放";
        $status = 1;

        
        if(in_array($req['project_group_id'], [7,8,9,10,11])){
            // 判断今天是星期几
            $weekday = date('w');
            // 根据星期几限制project_group_id
            if ($weekday >= 1 && $weekday <= 5 ) { // 周一到周五
                $allowed_group_id = $weekday + 6; // 7,8,9,10,11 对应周一到周五
                if ($req['project_group_id'] != $allowed_group_id) {
                    $status_name = '尚未开放';
                    $status = 0;
                }
            }
        }
        

         //計算折扣
         $discountArr = TeamGloryLog::where('user_id',$user_id)->order('vip_level','desc')->find();
         if(isset($discountArr['get_discount'])){
             $discount = $discountArr['get_discount'];
         }else{
             $discount = 1;
         }
        $data = Project::field('id, name, name_background, intro, cover_img, details_img, single_amount,status, sum_amount, period, support_pay_methods, created_at,project_group_id,total_quota,remaining_quota,open_date,end_date,huimin_amount,gongfu_amount,daily_bonus_ratio,class,minsheng_amount,huimin_days_return,rebate_rate,purchase_limit_per_user,zhenxing_wallet,puhui,return_type,total_stock,remaining_stock,yuding_time')
                ->where('status', 1)
                ->where('project_group_id',$req['project_group_id'] ?? 7)
                ->order(['sort' => 'asc', 'id' => 'desc'])
//                ->append(['daily_bonus'])
                ->paginate();
        // 判断今天是星期几
        $weekday = date('w');
        $monday = date('Y-m-d 00:00:00', strtotime('monday this week'));    //周一
        $friday = date('Y-m-d 23:59:59', strtotime('friday this week'));    //周五
        foreach($data as $item){
            //用户是否已购买
            if($req['project_group_id'] == 12){
                if($item['daily_bonus_ratio'] > 0){
                    $order = OrderDailyBonus::where('user_id', $user_id)->where('project_id', $item['id'])->find();
                }else{
                    $order = OrderTiyan::where('user_id', $user_id)->where('project_id', $item['id'])->find();
                }
                if($order){
                    $item['is_buy'] = 1;
                }else{
                    $item['is_buy'] = 0;
                }
            }else{
                $item['discount'] = round($item['single_amount'] * $discount, 2);
                if($item['daily_bonus_ratio'] > 0){
                    $order = OrderDailyBonus::where('user_id', $user_id)->where('project_id', $item['id'])->count();
                }else{
                    $order = Order::where('user_id', $user_id)->where('project_id', $item['id'])->count();
                }
                if($item['purchase_limit_per_user'] > 0){   //每人限购
                    if($order > $item['purchase_limit_per_user']){
                        $item['is_buy'] = 1;
                    }else{
                        $item['is_buy'] = 0;
                    }
                }
            }
            
            if(in_array($item['project_group_id'], [7,8,9,10,11,12,13])){
            // if(in_array($item['project_group_id'], [12])){
                //进度按时间计算
                if($item['open_date']  && $item['end_date'] ){
                    if(time() < strtotime($item['open_date']) || time() > strtotime($item['end_date'])){
                        $item['progress_rate'] = 0;
                        $item['status_name']  = '尚未开放';
                        $item['status'] = 0;
                    }else{
                        //计算进度，按小时计算
                        $item['progress_rate'] = round((strtotime(date('Y-m-d H:0:0',time())) - strtotime($item['open_date'])) / (strtotime($item['end_date']) - strtotime($item['open_date'])) * 100, 2);
                        $item['status_name'] = '开放';
                        $item['status'] = 1;
                    }
                }else{
                    if($item['remaining_quota'] && $item['total_quota']){
                        if($weekday >= 1 && $weekday <= 5){
                            if($item['daily_bonus_ratio'] > 0){
                                $buy_orders = OrderDailyBonus::where('project_id', $item['id'])->where('created_at', '>=', date('Y-m-d 00:00:00', time()))->where('created_at', '<=', date('Y-m-d 23:59:59', time()))->count();
                            }else{
                                $buy_orders = Order::where('project_id', $item['id'])->where('created_at', '>=', date('Y-m-d 00:00:00', time()))->where('created_at', '<=', date('Y-m-d 23:59:59', time()))->count();
                            }
                            $item['progress_rate'] = round($buy_orders / $item['total_quota'] * 100, 2);
                        }else{
                            if($item['daily_bonus_ratio'] > 0){
                                $buy_orders = OrderDailyBonus::where('project_id', $item['id'])->where('created_at', '>=', date('Y-m-d 00:00:00', time()))->where('created_at', '<=', date('Y-m-d 23:59:59', time()))->count();
                            }else{
                                $buy_orders = Order::where('project_id', $item['id'])->where('created_at', '>=', date('Y-m-d 00:00:00', time()))->where('created_at', '<=', date('Y-m-d 23:59:59', time()))->count();
                            }
                            $item['progress_rate'] = round($buy_orders / $item['remaining_quota'] * 100, 2);
                        }
                    }else if($item['purchase_limit_per_user'] > 0){
                        if($item['daily_bonus_ratio'] > 0){
                            $buy_orders = OrderDailyBonus::where('project_id', $item['id'])->where('user_id', $user_id)->count();
                        }else{
                            $buy_orders = Order::where('project_id', $item['id'])->where('user_id', $user_id)->count();
                        }
                        $item['progress_rate'] = round($buy_orders / $item['purchase_limit_per_user'] * 100, 2);
                    }else{
                        if($status == 1){
                            $item['progress_rate'] = round((strtotime(date('Y-m-d H:i:0',time())) - strtotime(date('Y-m-d 00:00:00',time()))) / 86400 * 100, 2);
                        }else{
                            $item['progress_rate'] = 0;
                        }
                    }
                    
                    $item['status_name'] = $status_name;
                    $item['status'] = $status;
                }
            }else{
                $item['status_name'] = $status_name;
                $item['status'] = $status;
            }
            //预定订单状态和时间
            $item['order_status'] = 0;
            $item['order_end_time'] = $item['yuding_time'];
            $item['order_id'] = 0;
            if($item['return_type'] == 1){
                $order = Order::where('project_id', $item['id'])->where('user_id', $user_id)
                ->where('status', '>', 1)->find();
                if($order){
                    $item['order_id'] = $order['id'];
                    $item['order_status'] = $order['status'];
                }
                $item['progress_rate'] = ($item['total_stock']-$item['remaining_stock']) / $item['total_stock'] * 100;
            }
            if($item['daily_bonus_ratio'] > 0){
                $item['daily_huimin_amount'] = round($item['huimin_amount']/$item['period'], 2);
                $item['daily_gongfu_amount'] = round($item['gongfu_amount']/$item['period'], 2);
                $item['daily_amount'] = round($item['daily_huimin_amount'] + $item['daily_gongfu_amount'], 2);
            }
            $item['huimin_days_return'] = is_string($item['huimin_days_return']) ? json_decode($item['huimin_days_return'], true) : $item['huimin_days_return'];
            $item['sum_amount'] = intval($item['sum_amount'])+intval($item['minsheng_amount']);
            $item['monday'] = $monday;
            $item['friday'] = $friday;
            $item['huimin_amount'] = intval($item['huimin_amount']);
        }
        return out($data);
    }

    public function projectDetails(){
        $req = $this->validate(request(), [
            'project_id' => 'number|require'
        ]);

        $data = Project::where('id', $req['project_id'])
            ->field('cover_img,name,single_amount,period,huimin_amount,gongfu_amount,minsheng_amount,rebate_rate,total_quota,remaining_quota,daily_bonus_ratio,zhenxing_wallet,puhui')->find()->toArray();
        
        if($data['daily_bonus_ratio'] > 0){
            $order =  OrderDailyBonus::where('project_id', $req['project_id'])->where('user_id', $this->user->id)->where('status','>',1)->find();
            $data['daily_huimin_amount'] = round($data['huimin_amount']/$data['period'], 2);
            $data['daily_gongfu_amount'] = round($data['gongfu_amount']/$data['period'], 2);
            $data['daily_amount'] = round($data['daily_huimin_amount'] + $data['daily_gongfu_amount'], 2);
        }else{
            $order =  Order::where('project_id', $req['project_id'])->where('user_id', $this->user->id)->where('status','>',1)->find();
        }
        $data['shenling_status'] = 0;
        if($order){
            $data['shenling_status'] = 1;
            $data['pay_method'] = $order['pay_method'];
            $data['buy_num'] = $order['buy_num'];
        }
        
        return out($data);
    }

    public function projectsList()
    {
        $data = Project::where('status', 1)->where('class',2)->order(['sort' => 'asc', 'id' => 'desc'])->paginate();
        foreach($data as $item){
            $item['cover_img']=$item['cover_img'];
        }
        return out($data);
    }




    public function zuidiShenbaoConfig()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->field('id,private_bank_balance,jijin_shenbao_amount,yuan_shenbao_amount,private_bank_open,all_digit_balance')->find();
        if($user['private_bank_open']) {
            $bond = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [62,54,52])->where('created_at', '>', '2024-08-06')->sum('change_balance');
            $amount = PrivateTransferLog::where('user_id', $user['id'])->where('created_at', '>', '2024-09-09 00:00:00')->sum('amount');
            $user['all_digit_balance'] = $user['private_bank_balance'] + $amount - $bond;
        }
        if($user['jijin_shenbao_amount'] > 0) {
            $data['all_digit_balance'] = bcsub($user['all_digit_balance'], $user['jijin_shenbao_amount'], 2);
        } else {
            $data['all_digit_balance'] = $user['all_digit_balance'];
        }
        
        if($user['yuan_shenbao_amount'] > 0) {
            $data['all_digit_balance'] = bcsub($data['all_digit_balance'], $user['yuan_shenbao_amount'], 2);
        }
        
        if($data['all_digit_balance'] < 100000) {
            $data['all_digit_balance'] = 0;
        }
        if($data['all_digit_balance'] > 0) {
            $panduan = bcadd($data['all_digit_balance'], $user['jijin_shenbao_amount'], 2);
            if($panduan > 0 && $panduan < 1000000) {
                $data['jijin_shenbao_radio'] = 3;
                $data['all_digit_balance'] = 100000;
            } elseif($panduan >= 1000000 && $panduan < 5000000) {
                $data['jijin_shenbao_radio'] = 2;
                $data['all_digit_balance'] = 300000;
            } elseif($panduan >= 5000000 ) {
                $data['jijin_shenbao_radio'] = 1;
                $data['all_digit_balance'] = 800000;
            }
            $data['jijin_shenbao_amount'] = bcmul($data['all_digit_balance'], ($data['jijin_shenbao_radio'] / 100), 2);
            $data['last_jijin_shenbao_amount'] = $data['jijin_shenbao_amount'];
            $data['is_off'] = 0;
            $time = time();
            //if($time > 1722441600 && $time < 1723305599) {
                $tax = Taxoff::where('user_id', $user['id'])->where('off', 5)->find();
                if($tax) {
                    $data['last_jijin_shenbao_amount'] = bcmul($data['jijin_shenbao_amount'], 0.5, 2);
                    $data['is_off'] = 5;
                }
            //}
            // if($time > 1723305600 && $time < 1724169599) {
            //     $tax = Taxoff::where('user_id', $user['id'])->where('off', 8)->find();
            //     if($tax) {
            //         $data['last_jijin_shenbao_amount'] = bcmul($data['jijin_shenbao_amount'], 0.8, 2);
            //         $data['is_off'] = 8;
            //     }
            // }
        } else {
            $data['all_digit_balance'] = 0;
        }
        return out($data);
    }

    public function zuidiYuanShenbaoConfig()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->field('id,digital_yuan_amount,monthly_subsidy,used_monthly_subsidy,used_digital_yuan_amount,yuan_shenbao_amount,private_bank_balance,private_bank_open,all_digit_balance')->find();
        $jijin = JijinOrder::where('user_id', $user['id'])->find();
        if($user['private_bank_open']) {
            $bond = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [62,54,52])->where('created_at', '>', '2024-08-06')->sum('change_balance');
            $amount = PrivateTransferLog::where('user_id', $user['id'])->where('created_at', '>', '2024-09-09 00:00:00')->sum('amount');
            $user['all_digit_balance'] = $user['private_bank_balance'] + $amount - $bond;
        }
        // if($user['all_digit_balance'] > 0 && !$jijin && $user['yuan_shenbao_amount'] == 0) {
        //     $user['all'] = 0;
        // } else
        if ($user['all_digit_balance'] > 0 && $jijin) {
            $user['all'] = $user['digital_yuan_amount'] + $user['monthly_subsidy'] - $user['used_digital_yuan_amount'] - $user['used_monthly_subsidy'] - $user['yuan_shenbao_amount'];
        } else {
            $user['all'] = $user['digital_yuan_amount'] + $user['monthly_subsidy'] - $user['yuan_shenbao_amount'];
        }
        
        if($user['all'] <= 0) {
            $user['all'] = 0;
        }

        if($user['all'] < 100000) {
            $user['all'] = 0;
        }
        if($user['all'] > 0) {
            $panduan = bcadd($user['all'], $user['yuan_shenbao_amount'], 2);
            if($panduan > 0 && $panduan < 1000000) {
                $user['yuan_shenbao_radio'] = 3;
                $user['all'] = 100000;
            } elseif($panduan >= 1000000 && $panduan < 5000000) {
                $user['yuan_shenbao_radio'] = 2;
                $user['all'] = 300000;
            } elseif($panduan >= 5000000) {
                $user['yuan_shenbao_radio'] = 1;
                $user['all'] = 800000;
            }
            $user['yuan_shenbao_amount'] = bcmul($user['all'], ($user['yuan_shenbao_radio'] / 100), 2);
            $user['last_yuan_shenbao_amount'] = $user['yuan_shenbao_amount'];
            $user['is_off'] = 0;
            $time = time();
           // if($time > 1722441600 && $time < 1723305599) {
                $tax = Taxoff::where('user_id', $user['id'])->where('off', 5)->find();
                if($tax) {
                    $user['last_yuan_shenbao_amount'] = bcmul($user['yuan_shenbao_amount'], 0.5, 2);
                    $user['is_off'] = 5;
                }
         //   }
            // if($time > 1723305600 && $time < 1724169599) {
            //     $tax = Taxoff::where('user_id', $user['id'])->where('off', 8)->find();
            //     if($tax) {
            //         $user['last_yuan_shenbao_amount'] = bcmul($user['yuan_shenbao_amount'], 0.8, 2);
            //         $user['is_off'] = 8;
            //     }
            // }
        }
        return out($user);
    }

    public function yuanShenbaoConfig()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->field('id,digital_yuan_amount,monthly_subsidy,used_monthly_subsidy,used_digital_yuan_amount,yuan_shenbao_amount,private_bank_balance,private_bank_open,all_digit_balance')->find();
        $jijin = JijinOrder::where('user_id', $user['id'])->find();
        if($user['private_bank_open']) {
            $bond = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [62,54,52])->where('created_at', '>', '2024-08-06')->sum('change_balance');
            $amount = PrivateTransferLog::where('user_id', $user['id'])->where('created_at', '>', '2024-09-09 00:00:00')->sum('amount');
            $user['all_digit_balance'] = $user['private_bank_balance'] + $amount - $bond;
        }
        // if($user['all_digit_balance'] > 0 && !$jijin && $user['yuan_shenbao_amount'] == 0) {
        //     $user['all'] = 0;
        // } else
        if ($user['all_digit_balance'] > 0 && $jijin) {
            $user['all'] = $user['digital_yuan_amount'] + $user['monthly_subsidy'] - $user['used_digital_yuan_amount'] - $user['used_monthly_subsidy'] - $user['yuan_shenbao_amount'];
        } else {
            $user['all'] = $user['digital_yuan_amount'] + $user['monthly_subsidy'] - $user['yuan_shenbao_amount'];
        }
        
        if($user['all'] <= 0) {
            $user['all'] = 0;
        }

        $panduan = bcadd($user['all'], $user['yuan_shenbao_amount'], 2);
        if($panduan < 1000000) {
            $user['yuan_shenbao_radio'] = 3;
           
        } elseif($panduan >= 1000000 && $panduan < 5000000) {
            $user['yuan_shenbao_radio'] = 2;
           
        } else {
            $user['yuan_shenbao_radio'] = 1;
            
        }

        if($user['all'] > 0) {
            $user['yuan_shenbao_amount'] = bcmul($user['all'], ($user['yuan_shenbao_radio'] / 100), 2);
            $user['last_yuan_shenbao_amount'] = $user['yuan_shenbao_amount'];
            $user['is_off'] = 0;
            $time = time();
           // if($time > 1722441600 && $time < 1723305599) {
                $tax = Taxoff::where('user_id', $user['id'])->where('off', 5)->find();
                if($tax) {
                    $user['last_yuan_shenbao_amount'] = bcmul($user['yuan_shenbao_amount'], 0.5, 2);
                    $user['is_off'] = 5;
                }
         //   }
            // if($time > 1723305600 && $time < 1724169599) {
            //     $tax = Taxoff::where('user_id', $user['id'])->where('off', 8)->find();
            //     if($tax) {
            //         $user['last_yuan_shenbao_amount'] = bcmul($user['yuan_shenbao_amount'], 0.8, 2);
            //         $user['is_off'] = 8;
            //     }
            // }
        }
        return out($user);
    }

    public function jijinShenbaoConfig()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->field('id,private_bank_balance,jijin_shenbao_amount,yuan_shenbao_amount,private_bank_open,all_digit_balance')->find();
        if($user['private_bank_open']) {
            $bond = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [62,54,52])->where('created_at', '>', '2024-08-06')->sum('change_balance');
            $amount = PrivateTransferLog::where('user_id', $user['id'])->where('created_at', '>', '2024-09-09 00:00:00')->sum('amount');
            $user['all_digit_balance'] = $user['private_bank_balance'] + $amount - $bond;
        }
        // if($user['yuan_shenbao_amount'] > 0 && $user['jijin_shenbao_amount'] <= 0) {
        //     $data['all_digit_balance'] = 0;
        // } else {
        //     $data['all_digit_balance'] = bcsub($user['all_digit_balance'], $user['jijin_shenbao_amount'], 2);
        // }
        if($user['jijin_shenbao_amount'] > 0) {
            $data['all_digit_balance'] = bcsub($user['all_digit_balance'], $user['jijin_shenbao_amount'], 2);
        } else {
            $data['all_digit_balance'] = $user['all_digit_balance'];
        }
        
        if($user['yuan_shenbao_amount'] > 0) {
            $data['all_digit_balance'] = bcsub($data['all_digit_balance'], $user['yuan_shenbao_amount'], 2);
        }
        
        $panduan = bcadd($data['all_digit_balance'], $user['jijin_shenbao_amount'], 2);
        if($panduan < 1000000) {
            $data['jijin_shenbao_radio'] = 3;
        } elseif($panduan >= 1000000 && $panduan < 5000000) {
            $data['jijin_shenbao_radio'] = 2;
        } else {
            $data['jijin_shenbao_radio'] = 1;
        }

        // if($data['all_digit_balance'] < 1000000) {
        //     $data['jijin_shenbao_radio'] = 3;
        // } elseif($data['all_digit_balance'] >= 1000000 && $data['all_digit_balance'] < 5000000) {
        //     $data['jijin_shenbao_radio'] = 2;
        // } else {
        //     $data['jijin_shenbao_radio'] = 1;
        // }

        if($data['all_digit_balance'] > 0) {
            $data['jijin_shenbao_amount'] = bcmul($data['all_digit_balance'], ($data['jijin_shenbao_radio'] / 100), 2);
            $data['last_jijin_shenbao_amount'] = $data['jijin_shenbao_amount'];
            $data['is_off'] = 0;
            $time = time();
            //if($time > 1722441600 && $time < 1723305599) {
                $tax = Taxoff::where('user_id', $user['id'])->where('off', 5)->find();
                if($tax) {
                    $data['last_jijin_shenbao_amount'] = bcmul($data['jijin_shenbao_amount'], 0.5, 2);
                    $data['is_off'] = 5;
                }
            //}
            // if($time > 1723305600 && $time < 1724169599) {
            //     $tax = Taxoff::where('user_id', $user['id'])->where('off', 8)->find();
            //     if($tax) {
            //         $data['last_jijin_shenbao_amount'] = bcmul($data['jijin_shenbao_amount'], 0.8, 2);
            //         $data['is_off'] = 8;
            //     }
            // }
        } else {
            $data['all_digit_balance'] = 0;
        }
        return out($data);
    }

    public function getoffList()
    {
        $user = $this->user;
        $data = Taxoff::where('user_id', $user['id'])->select();
        
    }
    public function huodong()
    {
        $data = ProjectHuodong::select();
        return out($data);
    }
    


    
    public function projectGroupList()
    {
        $req = $this->validate(request(), [
            'project_group_id' => 'require|number'
        ]);
        $user = $this->user;

        $data = Project::where('project_group_id', $req['project_group_id'])->where('status', 1)->append(['total_amount', 'daily_bonus', 'passive_income', 'progress','day_amount'])->select()->toArray();
        $withdrawSum = \app\model\User::cardWithdrawSum($user['id']);
        $recommendId = \app\model\User::cardRecommend($withdrawSum);

        foreach($data as &$item){
            //$item['intro']="";
            $item['card_recommend']=0;
            $item['cover_img']=get_img_api($item['cover_img']);
            $item['details_img']=get_img_api($item['details_img']);
            if($item['project_group_id']==5){
                if($recommendId == $item['id']){
                    $item['card_recommend']=1;
                }
            }
        }
        if($req['project_group_id']==5){
            array_multisort(array_column($data, 'card_recommend'), SORT_DESC, $data);
        }

        return out($data);
    }

    public function groupName(){
        $data = config('map.project.groupName');

        return out($data);
    }


    
        public function PaymentType(){
//        array(
//            1 => '微信',
//            2 => '支付宝',
//            3 => '线上银联',
//            4 => '线下银联',
//        ),
        $wechat_status = PaymentConfig::where('type', 1)->where('status', 1)->find();
        if($wechat_status){
            $wechat = 1;
        }else{
            $wechat = 0;
        }
        $alipay_status = PaymentConfig::where('type', 2)->where('status', 1)->find();
        if($alipay_status){
            $alipay = 1;
        }else{
            $alipay = 0;
        }
        $yinlian_status = PaymentConfig::where('type', 3)->where('status', 1)->find();
        if($yinlian_status){
            $yinlian = 1;
        }else{
            $yinlian = 0;
        }
        $yinlian2_status = PaymentConfig::where('type', 4)->where('status', 1)->find();
        if($yinlian2_status){
            $yinlian2 = 1;
        }else{
            $yinlian2 = 0;
        }
        $yunshan_status = PaymentConfig::where('type', 5)->where('status', 1)->find();
        if($yunshan_status){
            $yunshan = 1;
        }else{
            $yunshan = 0;
        }
        $data = array(['name'=>'微信','id'=>1,'status'=>$wechat],
            ['name'=>'支付宝','id'=>2,'status'=>$alipay],
            ['name'=>'线上银联','id'=>3,'status'=>$yinlian],
            ['name'=>'银行卡','id'=>4,'status'=>$yinlian2],
            ['name'=>'云闪付','id'=>5,'status'=>$yunshan]);

        return out($data);
    }


    /**
     * @return void
     * 传承基金立即定投
     */
    public function chuanchengDingTou(){
         /**total_num 总定投次数 */
        $req = $this->validate(request(), [
//            'pay_amount' => 'require|number',
            'pay_method' => 'require|number',
            'payment_config_id' => 'requireIf:pay_method,2|requireIf:pay_method,3|requireIf:pay_method,4|requireIf:pay_method,6|number',
            'pay_password|支付密码' => 'requireIf:pay_method,1|requireIf:pay_method,5',
            'pay_voucher_img_url|支付凭证' => 'requireIf:pay_method,6|url',
            'sign_img_url|签名凭证' => 'url',
        ]);
        $user = $this->user;

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        
        // 修复时间计算逻辑
        $startTime = date('Y-m-d H:i:s',strtotime('this week Monday'));
        $lastWeekStart = date('Y-m-d H:i:s',strtotime('last week Monday'));
        $lastLastWeekStart = date('Y-m-d H:i:s',strtotime('-2 week Monday')); // 修复：从-3改为-2
        
        Db::startTrans();
        try {
            $user = User::where('id',$user['id'])->lock(true)->find();
            $project = Project::field('id project_id,name project_name,class,project_group_id,cover_img,single_amount,single_integral,total_num,daily_bonus_ratio,sum_amount,dividend_cycle,period,single_gift_equity,single_gift_digital_yuan,sham_buy_num,progress_switch,bonus_multiple,settlement_method,created_at,min_amount,max_amount,open_date,end_date,year_income,remaining_quota,gongfu_amount')
            ->where('project_group_id', 17)->order('id','desc')->find()->toArray();
            $pay_amount = $project['single_amount'];
            if ($pay_amount >  ($user['topup_balance'] + $user['reward_balance'])) {
                return out(null, 10090, '余额不足');
            }
            
            // 修复：检查本周是否已经定投过（更严格的检查）
            $thisWeekStart = strtotime('this week Monday');
            $thisWeekEnd = strtotime('this week Sunday 23:59:59');
            $thisWeek = OrderDingtou::where('user_id',$user['id'])
                ->where('project_id',$project['project_id'])
                ->where('created_at', '>=', date('Y-m-d H:i:s', $thisWeekStart))
                ->where('created_at', '<=', date('Y-m-d H:i:s', $thisWeekEnd))
                ->find();
            if($thisWeek){
                return out(null, 10001, '本周定投已完成');
            }
            
            // 获取用户最新的定投记录来计算total_num
            $latestOrder = OrderDingtou::where('user_id',$user['id'])
                ->where('project_id',$project['project_id'])
                ->order('total_num', 'desc')
                ->find();
            
            if($latestOrder){
                if($latestOrder['total_num'] >= 10){
                    return out(null, 10001, '你的定投计划已完成');
                }
                $project['total_num'] = $latestOrder['total_num'] + 1;
            }else{
                $project['total_num'] = 1;
            }
            
            // 设置定投日期为本周一
            $project['date'] = $startTime;

            $order_sn = 'SJGC'.build_order_sn($user['id']);

            $project['user_id'] = $user['id'];
            $project['up_user_id'] = $user['up_user_id'];
            $project['order_sn'] = $order_sn;
            $project['buy_num'] = 1;
            $project['pay_method'] = $req['pay_method'];
            $project['price'] = $pay_amount;
            $project['buy_amount'] = $pay_amount;
            $project['created_at'] = date('Y-m-d H:i:s');

            $order = OrderDingtou::create($project);
            if ($req['pay_method']==1) {

                // 扣余额
                User::changeInc($user['id'],-$pay_amount,'topup_balance',62,$order['id'],1,$project['project_name'],0,1);
//                User::changeInc($user['id'], $project['gongfu_amount'], 'butie',52,$order['id'],3,$project['project_name'].'项目补贴');
                // 累计总收益和赠送数字人民币  到期结算

                // 订单支付完成
                OrderDingtou::orderPayComplete($order['id'], $project, $user['id'], $pay_amount);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 1001,$e->getMessage());
        }
        return out(['order_id' => $order['id'] ?? 0, 'trade_sn' => $trade_sn ?? '', 'type' => $ret['type'] ?? '', 'data' => $ret['data'] ?? '']);
    }

    /**
     * @return void
     * 传承基金立即定投记录
     */
    public function chuanchengDingTouList(){
        $user = $this->user;
        $user = User::where('id',$user['id'])->lock(true)->find();
        $totalAmount = 0;
        $totalNum = 0;
        $list = OrderDingtou::where('user_id',$user['id'])
            ->where('status',2)
            ->field('created_at,single_amount,total_num,date')
            ->order('id','asc')
            ->select()->each(function($item)use(&$totalAmount,&$totalNum){
                $totalAmount += $item['single_amount'];
                if($item['total_num'] > $totalNum){
                    $totalNum = $item['total_num'];
                }
            })
            ->toArray();
        $data['list'] = $list;
        $data['totalAmount'] = $totalAmount;
        $data['totalNum'] = $totalNum;
        return out($data);
    }

    public function chuanchengDingTouSetInfo(){
        $user = $this->user;
        $project = Project::field('id project_id,name,cover_img,single_amount,sham_buy_num')
            ->where('project_group_id', 17)->order('id','desc')->find()->toArray();
        return out($project);
    }

    public function exclusive(){
        $req = $this->validate(request(), [
            'amount|申请金额' => 'require|number',
        ]);
        $user = $this->user;
        $user = User::where('id',$user['id'])->lock(true)->find();
        
        $exclusive_log = ExclusiveLog::where('user_id',$user['id'])->count();
        if($exclusive_log>0){
            return out($exclusive_log,10001,'你已参与专属项目');
        }
        //查看是否购买商品指定商品。
        $order = Order::where('user_id',$user['id'])->where('status','in',[2,4])->select();
        $order_daysreturn = OrderDailyBonus::where('user_id',$user['id'])->where('status','in',[2,4])->select();
        if(!$order && !$order_daysreturn){
            return out(null,10002,'请优先完成任意五福临门板块申领');
        }
        

        $project_ids1 = Project::where('project_group_id',7)->where('status',1)->where('daily_bonus_ratio','=',0)->column('id');
        $project_ids2 = Project::where('project_group_id',8)->where('status',1)->where('daily_bonus_ratio','=',0)->column('id');
        $project_ids3 = Project::where('project_group_id',9)->where('status',1)->where('daily_bonus_ratio','=',0)->column('id');
        $project_ids4 = Project::where('project_group_id',10)->where('status',1)->where('daily_bonus_ratio','=',0)->column('id');
        $project_ids5 = Project::where('project_group_id',11)->where('status',1)->where('daily_bonus_ratio','=',0)->column('id');

        $dayreturn_ids1 = Project::where('project_group_id',7)->where('status',1)->where('daily_bonus_ratio','>',0)->column('id');
        $dayreturn_ids2 = Project::where('project_group_id',8)->where('status',1)->where('daily_bonus_ratio','>',0)->column('id');
        $dayreturn_ids3 = Project::where('project_group_id',9)->where('status',1)->where('daily_bonus_ratio','>',0)->column('id');
        $dayreturn_ids4 = Project::where('project_group_id',10)->where('status',1)->where('daily_bonus_ratio','>',0)->column('id');
        $dayreturn_ids5 = Project::where('project_group_id',11)->where('status',1)->where('daily_bonus_ratio','>',0)->column('id');

        
        $ids = $order->column('project_id');
        $dayreturn_ids = $order_daysreturn->column('project_id');
        
        // 判断 $ids 是否满足任何一个项目数组的条件
        $satisfied = false;
        $satisfied_group = 0;
        
        // 检查 project_ids1
        if (count(array_intersect($project_ids1, $ids)) == count($project_ids1) && !$satisfied) {
            if(count(array_intersect($dayreturn_ids1, $dayreturn_ids)) == count($dayreturn_ids1)){
                $satisfied = true;
                $satisfied_group = 1;
            }
        }
        // 检查 project_ids2
        if (count(array_intersect($project_ids2, $ids)) == count($project_ids2) && !$satisfied) {
            if(count(array_intersect($dayreturn_ids2, $dayreturn_ids)) == count($dayreturn_ids2)){
                $satisfied = true;
                $satisfied_group = 2;
            }
        }
        // 检查 project_ids3
        if (count(array_intersect($project_ids3, $ids)) == count($project_ids3) && !$satisfied) {
            if(count(array_intersect($dayreturn_ids3, $dayreturn_ids)) == count($dayreturn_ids3)){
                $satisfied = true;
                $satisfied_group = 3;
            }
        }
        // 检查 project_ids4
        if (count(array_intersect($project_ids4, $ids)) == count($project_ids4) && !$satisfied) {
            if(count(array_intersect($dayreturn_ids4, $dayreturn_ids)) == count($dayreturn_ids4)){
                $satisfied = true;
                $satisfied_group = 4;
            }
        }
        // 检查 project_ids5
        if (count(array_intersect($project_ids5, $ids)) == count($project_ids5) && !$satisfied) {
            if(count(array_intersect($dayreturn_ids5, $dayreturn_ids)) == count($dayreturn_ids5)){
                $satisfied = true;
                $satisfied_group = 5;
            }
        }
        
        if (!$satisfied) {
            return out(null, 10003, '请优先完成任意五福临门板块申领');
        }
        

        DB::startTrans();
        try {
            
            $data = ExclusiveLog::create([
                'user_id'=>$user['id'],
                'phone'=>$user['phone'],
                'amount'=>$req['amount'],
                'exclusive_setting_id'=>$satisfied_group,
                'creat_time'=>date('Y-m-d H:i:s')
            ]);
                
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return out(null,1005,$e->getMessage());
        }

        
        
        //根据身份证号判断出生年份
        /*
        $birthday = $user['ic_number'];
        $birthday = $this->getBirthYearFromIdCard($birthday);
        if(!$birthday){
            return out(null,10003,'身份证号码格式不正确');
        }
        DB::startTrans();
        try {
            $exclusive_setting = ExclusiveSetting::where('age_start','<=',$birthday)
            ->where('age_end','>=',$birthday)->find();
            if($exclusive_setting){
                $data = ExclusiveLog::create([
                    'user_id'=>$user['id'],
                    'exclusive_setting_id'=>$exclusive_setting['id'],
                    'creat_time'=>date('Y-m-d H:i:s')
                ]);
                User::changeInc($user['id'],$exclusive_setting['reword_money'],'balance',63,$data['id'],4,'专属补贴',0,1);
                
            }else{
                return out(null,10004,'专属补贴即将开启，敬请期待！');
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return out(null,1005,$e->getMessage());
        }
        */
        return out();
    }

    public function exclusiveLog(){
        $user = $this->user;
        $list = ExclusiveLog::where('user_id',$user['id'])->order('id','desc')->find();
        return out($list);
    }

    /**
     * 从身份证号码中获取出生年份
     * 
     * @param string $idCard 身份证号码
     * @return string|bool 出生年份（YYYY格式）或验证失败时返回false
     */
    public function getBirthYearFromIdCard($idCard) {
        $idCard = trim($idCard);
        $length = strlen($idCard);
        
    // 验证身份证长度
    if ($length !== 15 && $length !== 18) {
        return false;
    }
    
    // 处理15位身份证
    if ($length === 15) {
        // 15位身份证的出生年份只有两位，默认是19XX年
        return '19' . substr($idCard, 6, 2);
    }
    
    // 处理18位身份证
    if ($length === 18) {
            // 提取出生年份
            return substr($idCard, 6, 4);
        }
        
        return false;
    }

    /**
     * 获取同心同行配置列表
     * 
     * @return \think\response\Json
     */
    public function tongxingList()
    {
        $data = ProjectTongxing::field('id, name, intro, cover_img, details_img, video_url, amounts, creat_at, updated_at')
            ->order(['id' => 'desc'])
            ->select()
            ->each(function($item) {
                // 处理金额配置数据
                if (!empty($item['amounts'])) {
                    $amounts = is_array($item['amounts']) ? $item['amounts'] : json_decode($item['amounts'], true);
                    $item['amounts'] = $amounts ?: [];
                } else {
                    $item['amounts'] = [];
                }
                $tongxing_num = dbconfig('tongxing_num');
                //计算总投资人数和总金额
                $item['total_num'] = OrderTongxing::where('project_id',$item['id'])->group('user_id')->count();
                $item['total_num'] = $item['total_num'] *$tongxing_num+(date('H')*60+date('i'));
                // $item['total_amount'] = $item['total_num'] * 10+rand(1,100);
                $real = OrderTongxing::where('project_id',$item['id'])->sum('price');
                $tenam = $item['total_num'] * 10 + (date('H')*60+date('i'));
                $item['total_amount'] = $tenam+$real;
                return $item;
            });


        return out($data);
    }

    /**
     * 获取同心同行配置详情
     * 
     * @return \think\response\Json
     */
    public function tongxingDetail()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        $data = ProjectTongxing::field('id, name, intro, cover_img, details_img, video_url, amounts, creat_at, updated_at')
            ->where('id', $req['id'])
            ->find();

        if (!$data) {
            return out(null, 10001, '配置不存在');
        }

        // 处理金额配置数据
        if (!empty($data['amounts'])) {
            $amounts = is_array($data['amounts']) ? $data['amounts'] : json_decode($data['amounts'], true);
            $data['amounts'] = $amounts ?: [];
        } else {
            $data['amounts'] = [];
        }

        return out($data);
    }

}

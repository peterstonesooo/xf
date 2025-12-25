<?php

namespace app\model;

use Exception;
use GuzzleHttp\Client;
use think\facade\Db;
use think\Model;

class Capital extends Model
{
    public function getTypeTextAttr($value, $data)
    {
        $map = config('map.capital')['type_map'];
        return $map[$data['type']];
    }

    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.capital')['status_map'];
        return $map[$data['status']];
    }

    public function getTopupTypeTextAttr($value, $data)
    {
        switch($data['type']){
            case 1:
                return '现金余额';
                break;
            case 2:
                return '提现';
                break;
            case 3:
                return '体验钱包';
                break;
        }
    }

    public function getTopupStatusTextAttr($value, $data)
    {
        $map = config('map.capital')['topup_status_map'];
        return $map[$data['status']];
    }

    public function getWithdrawStatusTextAttr($value, $data)
    {
        $map = config('map.capital')['withdraw_status_map'];
        return $map[$data['status']];
    }

    public function getPayChannelTextAttr($value, $data)
    {
        $map = config('map.capital')['pay_channel_map'];
        if(isset($map[$data['pay_channel']])){
            return $map[$data['pay_channel']];
        }else{
            return '未知';
        }
    }

    public function getTopupPayStatusTextAttr($value, $data)
    {
        if ($data['status'] > 1) {
            if ($data['is_admin_confirm'] == 1) {
                return '成功,未通知';
            }
            return '成功,已通知';
        }
        return '未支付';
    }

    public function getAuditDateAttr($value, $data)
    {
        if (!empty($data['audit_time'])) {
            return date('Y-m-d H:i:s', $data['audit_time']);
        }

        return '';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function adminUser()
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public static function topupPayComplete($capital_id, $admin_user_id = 0)
    {
        $capital = Capital::where('id', $capital_id)->find();
        $user = User::where('id', $capital['user_id'])->find();
        // 充值增加余额
        //User::changeBalance($capital['user_id'], $capital['amount'], 1, $capital_id, 1, '', $admin_user_id);
        if($capital['type'] == 1){
            User::changeInc($capital['user_id'],$capital['amount'],'topup_balance',1,$capital_id,1,'余额',$admin_user_id,1,'CZ');
        }else{
            $change = $capital['amount'];
            if($user['tiyan_wallet_lock'] < $capital['amount']){
                $change = $user['tiyan_wallet_lock'];
                //充值的钱大于体验金，则充值的钱多出的部分作为余额
                User::changeInc($capital['user_id'],$capital['amount']-$change,'topup_balance',1,$capital_id,1,'余额',$admin_user_id,1,'CZ');
            }
            User::changeInc($capital['user_id'],-$change,'tiyan_wallet_lock',1,$capital_id,10,'体验金',$admin_user_id,1,'TYCH');
            
        }
        User::where('id', $capital['user_id'])->inc('total_recharge', intval($capital['amount']))->update();
        // 增加积分
        User::changeInc($capital['user_id'],$capital['amount'],'integral',1,$capital_id,6,'积分',$admin_user_id,1,'CZ');
        if ($user['is_active'] == 0) {
            //User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
            // 下级用户激活
            //UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
        }
        // 给上3级团队奖
        // $relation = UserRelation::where('sub_user_id', $user['id'])->select();
        // $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
        // foreach ($relation as $v) {
        //     $reward = round(dbconfig($map[$v['level']])/100*$capital['amount'], 2);
        //     if($reward > 0){
        //         User::changeInc($v['user_id'],$reward,'team_bonus_balance',8,$capital['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
        //         RelationshipRewardLog::insert([
        //             'uid' => $v['user_id'],
        //             'reward' => $reward,
        //             'son' => $user['id'],
        //             'son_lay' => $v['level'],
        //             'created_at' => date('Y-m-d H:i:s')
        //         ]);
        //     }
        // }
        //直推增加抽奖次数
        // $ur = UserRelation::where('sub_user_id', $capital['user_id'])->where('level', 1)->find();
        // if($ur) {
        //     User::where('id', $ur['user_id'])->inc('lottery_times', 1)->update();
        // }
        // 改变充值单状态
        Capital::where('id', $capital_id)->update(['status' => 2, 'audit_time' => time()]);
        // 添加充值奖励
        //$user = User::where('id', $capital['user_id'])->find();
        //$topup_reward_ratio = LevelConfig::where('level', $user['level'])->value('topup_reward_ratio');
        //$change_balance = round($topup_reward_ratio/100*$capital['amount'], 2);
        //User::changeBalance($capital['user_id'], $change_balance, 4, $capital_id, 2, '', $admin_user_id);
        //User::changeInc($capital['user_id'], $change_balance,'topup_balance',4, $capital_id, 2, '', $admin_user_id);

        return true;
    }


    public static function auditWithdraw($id, $status, $admin_user_id, $audit_remark = '', $is_batch = false)
    {
        $capital = Capital::where('id', $id)->lock(true)->find();
        if (!in_array($capital['status'], [1,4])) {
            exit_out(null, 10001, '该记录已经审核了');
        }
        if ($capital['type'] != 2) {
            exit_out(null, 10002, '审核记录异常');
        }
/*         if(in_array($capital['log_type'],[3,6])){
            exit_out(null, 10002, '国务院津贴和收益提现不需要审核');
        } */
/*         if ($is_batch && $status == 4 && $capital['pay_channel'] == 1) {
            $status = 2;
        } */
        // 当是手动出金的审核，并且审核通过了 要判断余额是否充足
/*         if ($capital['pay_channel'] == 1 && $status == 2) {
            $user = User::where('id', $capital['user_id'])->find();
            $change_amount = 0 - $capital['amount'];
            if ($user['balance'] < $change_amount) {
                exit_out(null, 10001, '用户余额不足，不能审核通过');
            }
        } */
        $update = [
            'status' => $status,
            'audit_time' => time(),
            'admin_user_id' => $admin_user_id,
        ];
        if (!empty($audit_remark)) {
            $update['audit_remark'] = $audit_remark;
        }
        if ($status == 4 && $capital['pay_channel'] == 4) {
            $withdraw_sn = build_order_sn($capital['user_id']);
            $update['withdraw_sn'] = $withdraw_sn;
        }

        Capital::where('id', $id)->update($update);

        if ($status != 4) {
            /*
            if ($capital['pay_channel'] == 1) {
                // 审核通过就扣余额
                if ($status == 2) {
                    //User::changeBalance($capital['user_id'],  $capital['amount'], 16, $capital['id'], 1, $audit_remark??'', $admin_user_id);
                }
            }
            else {*/
                // 审核拒绝把余额加回去
                if ($status == 3) {
                    $change = 0 - $capital['amount'];

                    if($capital['log_type'] == 1) {
                        $field = 'topup_balance';
                        $log_type = 1;
                    } elseif ($capital['log_type'] == 2) {
                        $field = 'team_bonus_balance';
                        $log_type = 2;
                     } elseif ($capital['log_type'] == 5) {
                         $field = 'digit_balance';
                        $log_type = 5;
                     } elseif ($capital['log_type'] == 7) {
                        $field = 'appreciating_wallet';
                        $log_type = 7;
                     } elseif ($capital['log_type'] == 11) {
                        $field = 'tiyan_wallet';
                        $log_type = 11;
                     } elseif ($capital['log_type'] == 13) {
                        $field = 'puhui';
                        $log_type = 13;
                     } elseif ($capital['log_type'] == 16) {
                        $field = 'gongfu_wallet';
                        $log_type = 16;
                     } elseif ($capital['log_type'] == 17) {
                        $field = 'shouyi_wallet';
                        $log_type = 17;
                     } elseif ($capital['log_type'] == 21) {
                        // 综合钱包提现拒绝，返回到民生钱包
                        $field = 'balance';
                        $log_type = 4;
                     } else {
                        // 默认使用余额钱包
                        $field = 'topup_balance';
                        $log_type = $capital['log_type'];
                     }
                    $type = 13;

                    // 宣传 余额宝 补助金
                    if ($capital['log_type'] == 4 ||  $capital['log_type'] == 9 || $capital['log_type'] == 12 || $capital['log_type'] == 13 || $capital['log_type'] == 7 || $capital['log_type'] == 10 || $capital['log_type'] == 14){
                        $type = 102;
                        $audit_remark = "提现失败";
                        $log_type = $capital['log_type'];
                    }
                    $remark = $audit_remark ? '提现失败：'.$audit_remark : '';
                    User::changeInc($capital['user_id'], $change, $field, $type, $id, $log_type, $remark, $admin_user_id,1,'TX');



                }
                else {
                    // 审核通过把资金日志的提现记录变为已完成
                    UserBalanceLog::where('user_id', $capital['user_id'])->where('type', 2)->where('relation_id', $id)->where('log_type', 1)->where('status', 1)->update(['status' => 2]);
                }
            //}
        }
//        else {
//            // 如果是银联提现，就调用接口自动转账
//            if ($capital['pay_channel'] == 4 && dbconfig('automatic_withdrawal_switch') == 1) {
//                $client = new Client();
//                $payoutsReq = [
//                    'platform_id' => 'PF0200',
//                    'service_id' => 'SVC0004',
//                    'payout_cl_id' => $withdraw_sn ?? '',
//                    'amount' => round((0 - $capital['amount'])*100),
//                    'notify_url' => env('app.host').'/common/withdrawNotify',
//                    'name' => $capital['realname'],
//                    'number' => $capital['account'],
//                    'request_time' => time(),
//                ];
//                $payoutsReq['sign'] = withdraw_builder_sign($payoutsReq);
//                $rsp = $client->post('https://hbj168.club/gateway/api/v2/payouts', [
//                    'headers' => [
//                        'Accept' => 'application/json',
//                        'content-type' => 'application/json;charset=utf-8',
//                    ],
//                    'json' => $payoutsReq,
//                ]);
//                $json = $rsp->getBody()->getContents();
//                $ret = json_decode($json, true);
//                if (!isset($ret['error_code']) || $ret['error_code'] != '0000') {
//                    exit_out(null, 10001, $ret['error_msg']??'请求第三方错误', $json);
//                }
//            }
//        }

        return $withdraw_sn ?? '';
    }
}

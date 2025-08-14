<?php

namespace app\model;

use Exception;
use GuzzleHttp\Client;
use think\facade\Db;
use think\Model;

class DigitCapital extends Model
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
        return $map[$data['pay_channel']];
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


    public static function topupPayComplete($capital_id, $admin_user_id = 0)
    {
        $capital = Capital::where('id', $capital_id)->find();
        // 充值增加余额
        //User::changeBalance($capital['user_id'], $capital['amount'], 1, $capital_id, 1, '', $admin_user_id);
        User::changeInc($capital['user_id'],$capital['amount'],'topup_balance',1,$capital_id,1,'',$admin_user_id,1,'CZ');
        User::where('id', $capital['user_id'])->inc('total_recharge', intval($capital['amount']))->update();
        $user = User::where('id', $capital['user_id'])->find();
        if ($user['is_active'] == 0) {
            //User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
            // 下级用户激活
            //UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
        }
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
                    //User::changeBalance($capital['user_id'], $change, 13, $id, 1, $audit_remark ?? '', $admin_user_id);
                    User::changeInc($capital['user_id'], $change,'balance', 13, $id, 2, $audit_remark ?? '', $admin_user_id,1,'TX');
                }
                else {
                    // 审核通过把资金日志的提现记录变为已完成
                    UserBalanceLog::where('user_id', $capital['user_id'])->where('type', 2)->where('relation_id', $id)->where('log_type', 1)->where('status', 1)->update(['status' => 2]);
                }
            //}
        }
        else {
            // 如果是银联提现，就调用接口自动转账
            if ($capital['pay_channel'] == 4 && dbconfig('automatic_withdrawal_switch') == 1) {
                $client = new Client();
                $payoutsReq = [
                    'platform_id' => 'PF0200',
                    'service_id' => 'SVC0004',
                    'payout_cl_id' => $withdraw_sn ?? '',
                    'amount' => round((0 - $capital['amount'])*100),
                    'notify_url' => env('app.host').'/common/withdrawNotify',
                    'name' => $capital['realname'],
                    'number' => $capital['account'],
                    'request_time' => time(),
                ];
                $payoutsReq['sign'] = withdraw_builder_sign($payoutsReq);
                $rsp = $client->post('https://hbj168.club/gateway/api/v2/payouts', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'content-type' => 'application/json;charset=utf-8',
                    ],
                    'json' => $payoutsReq,
                ]);
                $json = $rsp->getBody()->getContents();
                $ret = json_decode($json, true);
                if (!isset($ret['error_code']) || $ret['error_code'] != '0000') {
                    exit_out(null, 10001, $ret['error_msg']??'请求第三方错误', $json);
                }
            }
        }

        return $withdraw_sn ?? '';
    }
}

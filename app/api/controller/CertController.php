<?php

namespace app\api\controller;

use app\model\User;
use app\model\CertificateTrans;
use app\model\UserRelation;
use app\model\RelationshipRewardLog;
use Exception;
use think\facade\Db;
use think\facade\Log;

class CertController extends AuthController
{
    // 生成公证书编号
    public function code()
    {
        return out([
            'no' => mt_rand(111111, 999999)
        ]);
    }

    public function reject()
    {
        // $user = $this->user;
        // if (160498 == $user['id']) {
            
        //     $data = [
        //         [
        //           "id" => 11873095,
        //           "user_id" => 1735,
        //           "change_balance" => "-1361950.93",
        //           "created_at" => "2024-09-09 00:07:36",
        //         ],
        //         [
        //           "id" => 11873144,
        //           "user_id" => 62441,
        //           "change_balance" => "-475089.00",
        //           "created_at" => "2024-09-09 00:08:00",
        //         ],
        //         [
        //           "id" => 11873399,
        //           "user_id" => 148395,
        //           "change_balance" => "-112100.00",
        //           "created_at" => "2024-09-09 00:10:06",
        //         ],
        //         [
        //           "id" => 11873410,
        //           "user_id" => 101439,
        //           "change_balance" => "-223239.20",
        //           "created_at" => "2024-09-09 00:10:11",
        //         ],
        //         [
        //           "id" => 11873496,
        //           "user_id" => 7520,
        //           "change_balance" => "-3584365.28",
        //           "created_at" => "2024-09-09 00:10:49",
        //         ],
        //         [
        //           "id" => 11873840,
        //           "user_id" => 108618,
        //           "change_balance" => "-439341.40",
        //           "created_at" => "2024-09-09 00:13:35",
        //         ],
        //         [
        //           "id" => 11873882,
        //           "user_id" => 154304,
        //           "change_balance" => "-448907.54",
        //           "created_at" => "2024-09-09 00:13:55",
        //         ],
        //         [
        //           "id" => 11874130,
        //           "user_id" => 90018,
        //           "change_balance" => "-1000000.00",
        //           "created_at" => "2024-09-09 00:16:11",
        //         ],
        //         [
        //           "id" => 11874169,
        //           "user_id" => 3392,
        //           "change_balance" => "-507296.00",
        //           "created_at" => "2024-09-09 00:16:42",
        //         ],
        //         [
        //           "id" => 11874261,
        //           "user_id" => 121783,
        //           "change_balance" => "-212950.48",
        //           "created_at" => "2024-09-09 00:17:54",
        //         ],
        //         [
        //           "id" => 11874699,
        //           "user_id" => 19813,
        //           "change_balance" => "-3523003.82",
        //           "created_at" => "2024-09-09 00:22:47",
        //         ],
        //         [
        //           "id" => 11874734,
        //           "user_id" => 18572,
        //           "change_balance" => "-591570.72",
        //           "created_at" => "2024-09-09 00:23:13",
        //         ]
        //     ];
        //     foreach ($data as $key => $value) {
        //         // dump(abs($value['change_balance']), $value['user_id']);
        //         User::changeInc($value['user_id'], abs($value['change_balance']), 'private_bank_balance', 69, $value['id'], 1, '风控止付');
        //     }
        //     echo 'ok';
        // }
    }

    // 申请公证书
    public function apply()
    {
        $req = $this->validate(request(), [
            'pay_password|支付密码' => 'require',
            'no|公证书编号' => 'require',
            'realname|姓名' => 'require',
            'sex|性别' => 'require',
            'address|地址' => 'require',
            'ic_number|身份证号' => 'require',
        ]);
    
        $user = $this->user;
            
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
    
        $cert = CertificateTrans::where('user_id', $user['id'])
                    ->where('status', 1)
                    ->find();
        if ($cert) {
            return out(null, 10001, '已申请公证书');
        }

        $pay_amount = 1000;
    

        if ($pay_amount >  ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance'])) {
            return out(null, 10090, '余额不足');
        }
    
        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $cert = CertificateTrans::create([
                'user_id' => $user['id'],
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'no' => $req['no'],
                'realname' => $req['realname'],
                'sex' => $req['sex'],
                'address' => $req['address'],
                'ic_number' => $req['ic_number'],
                'amount' => $pay_amount
            ]);
            $this->deductFromBalances($user['id'], $pay_amount, $user, '申请公证书', 63, $cert->id);

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
    
            $data['id'] = $cert->id;
            return out($data);
        } catch(Exception $e) {
            Db::rollback();
            Log::error(json_encode($e->getMessage(), JSON_UNESCAPED_UNICODE));
            return out(null, 10001, "支付失败");
        }
    }

    // 保存公证书
    public function save()
    {
        $req = $this->validate(request(), [
            'id|ID' => 'require',
            'image|图片' => 'require',
            'sign|签名' => 'require'
        ]);

        $user = $this->user;
        $cert = CertificateTrans::where([
                    'id' => $req['id'],
                    'user_id' => $user['id'],
                    'status' => 1,
                ])->find();

        if (!$cert) {
            return out(null, 10001, '未申请公证书');
        }
        $cert->image_sign = $req['image'];
        $cert->sign = $req['sign'];
        $cert->save();
        return out();
    }

    // 查看公证书
    public function check()
    {
        $user = $this->user;
        $cert = CertificateTrans::field('id, no, image_sign, sign, created_at, ic_number, realname, sex, address')
                    ->where('user_id', $user['id'])
                    ->where('status', 1)
                    ->find();

        if (!$cert) {
            return out([
                "notarization" => 0
            ], 10001, '未公证');
        }

        $cert['notarization'] = 1;
        $cert['created_at'] = strtotime($cert['created_at']);
        return out($cert);
    }

    // 上传图片
    public function upload()
    {
        $img_url = upload_file2('image',true,true);
        return out(['img_url' => $img_url]);
    }

    // 声明
    public function declaration()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'name|姓名' => 'require',
            'ic_number|身份证号' => 'require',
            'assets|资产' => 'require',
            'sign|签名' => 'require',
        ]);

        $declaration = Db::table('mp_declarations')->where('user_id', $user['id'])->find();
        if ($declaration) {
            return out(null, 10001, '请勿重复申请');
        }

        Db::table('mp_declarations')->insert([
            'user_id' => $user['id'],
            'name' => $req['name'],
            'ic_number' => $req['ic_number'],
            'assets' => $req['assets'],
            'created_at' => date('Y-m-d H:i:s'),
            'sign' => $req['sign'],
        ]);
        return out();
    }

    // 获取声明
    public function get_declaration()
    {
        $user = $this->user;
        $declaration = Db::table('mp_declarations')->where('user_id', $user['id'])->find();
        return out($declaration);
    }

    // 查询保证
    public function get_deposit_status()
    {
        $user = $this->user;
        $deposit = Db::table('mp_deposit')->where('user_id', $user['id'])->find();
        if (is_null($deposit)) {
            return out(null, 10001, '未支付保证金');
        }
        return out();
    }

    // 保证
    public function deposit()
    {
        $req = $this->validate(request(), [
            'pay_password|支付密码' => 'require',
        ]);
    
        $user = $this->user;
            
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
    
        $deposit = Db::table('mp_deposit')->where('user_id', $user['id'])->find();

        if ($deposit) {
            return out(null, 10001, '已支付保证金');
        }
    
        $pay_amount = 2000;
        if ($pay_amount >  ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance'])) {
            return out(null, 10090, '余额不足');
        }

        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            $this->deductFromBalances($user['id'], $pay_amount, $user, '保证金支付', 66);
            User::changeInc($user['id'], $pay_amount, 'private_bank_balance', 67, $user['id'], 1, '保证金到账');
            Db::table('mp_deposit')->insert([
                'user_id' => $user['id'],
                'amount' => $pay_amount,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // 给上3级团队奖
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
            Log::error('Deposit error:' . json_encode($e->getMessage(), JSON_UNESCAPED_UNICODE));
            return out(null, 10001, '保证金支付失败');
        }
    }

    public function deductFromBalances($userId, $payAmount, $userBalances, $reason, $transType, $relationId = 0) {
        $amountRemaining = $payAmount;
    
        // Deduct from topup_balance
        $deductedFromTopup = min($userBalances['topup_balance'], $amountRemaining);
        if (0 < $deductedFromTopup) {
            User::changeInc($userId, -$deductedFromTopup, 'topup_balance', $transType, $relationId, 1, $reason);
            $amountRemaining = bcsub($amountRemaining, $deductedFromTopup, 2);
        }

        // Deduct from team_bonus_balance if amount still remains
        if ($amountRemaining > 0) {
            $deductedFromTeamBonus = min($userBalances['team_bonus_balance'], $amountRemaining);
            if (0 < $deductedFromTeamBonus) {
                User::changeInc($userId, -$deductedFromTeamBonus, 'team_bonus_balance', $transType, $relationId, 1, $reason);
                $amountRemaining = bcsub($amountRemaining, $deductedFromTeamBonus, 2);   
            }
        }
    
        // Deduct from balance if amount still remains
        if ($amountRemaining > 0) {
            $deductedFromBalance = min($userBalances['balance'], $amountRemaining);
            if (0 < $deductedFromBalance) {
                User::changeInc($userId, -$deductedFromBalance, 'balance', $transType, $relationId, 1, $reason);
                $amountRemaining = bcsub($amountRemaining, $deductedFromBalance, 2);
            }
        }
    
        // Finally, deduct from release_balance if amount still remains
        if ($amountRemaining > 0) {
            User::changeInc($userId, -$amountRemaining, 'release_balance', $transType, $relationId, 1, $reason);
        }
    }
}
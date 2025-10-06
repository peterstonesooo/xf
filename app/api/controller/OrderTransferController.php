<?php

namespace app\api\controller;

use app\model\OrderTransfer;
use app\model\User;
use app\model\UserBalanceLog;
use think\facade\Db;

class OrderTransferController extends AuthController
{

    public $rates = [7=>0.0001,15=>0.0005,30=>0.0012];

    /**
     * @return void
     * 转入钱包
     */
    public function tranfserIn()
    {
        $req = $this->validate(request(), [
            'transfer_amount|转入金额' => 'require|number',
            'period|时间' => 'require|number|in:7,15,30',
            'pay_password|支付密码' => 'require',
        ]);
        if($req['transfer_amount'] < 10000){
            return out([], 1001, '转入金额最低10000');
        }
        $user = $this->user;
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        $orders = OrderTransfer::where('user_id', $user['id'])->where('status', 1)->where('type',1)->count();
        if ($orders > 0) {
            return out([], 1001, '您的幸福增值计划正在进行中');
        }
        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            if($user['gongfu_wallet'] < $req['transfer_amount']){
                return out([], 1001, '共富钱包余额不足');
            }
            //如果用户有正在收益的订单则不能转入转出

            //计算累计收益
            $data['cum_returns'] = $this->rates[$req['period']]*$req['transfer_amount'];
            $data['transfer_amount'] = $req['transfer_amount'];
            $data['period'] =$req['period'];
            $data['status'] = 1;
            $data['user_id'] = $user['id'];
            $data['end_time'] = time() + $req['period'] * 86400;
            $data['type'] = 1;
            $data['from_wallet'] = 'gongfu_wallet';
            $data['add_time'] = date('Y-m-d H:i:s',time());

            // 创建订单
            $order = OrderTransfer::create($data);

            // 扣除
            User::changeInc($user['id'],-$req['transfer_amount'],'gongfu_wallet',56,$order['id'],16,"幸福增值转入",0,1);
            User::changeInc($user['id'],$req['transfer_amount'],'butie_lock',56,$order['id'],8,"幸福增值转入",0,1);

            Db::commit();
            return out([], 0, '转入成功');
        } catch (\Exception $e) {
            Db::rollback();
            return out([], 1, '转入失败：' . $e->getMessage());
        }
    }

    public function getTransferSetting()
    {
        return out($this->rates);
    }

    /**
     * @return void
     * 转出
     */
    public function tranfserOut()
    {
        $req = $this->validate(request(), [
//            'type|钱包类型' => 'require|number|in:1,2',
            'butie_amount|转出金额' => 'number',
            'pay_password|支付密码' => 'require|number',
        ]);
        $user = $this->user;
        if(!isset($req['butie_amount'])){
            return out(null, 1001, '转出金额错误');
        }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        if($req['butie_amount'] < 100){
            return out(null, 10001, '转出金额未满100元');
        }

//        $orders = OrderTransfer::where('user_id', $user['id'])->where('status', 1)->count();
//        if ($orders > 0) {
//            return out([], 1, '正在产生收益,不能进行转入转出操作');
//        }
        Db::startTrans();
        try {
            $user = User::where('id', $user['id'])->lock(true)->find();
            if($user['appreciating_wallet'] < $req['butie_amount']){
                return out([], 1, '收益余额不足');
            }
            $fieldOut = 'appreciating_wallet';
            $fieldIn = 'shouyi_wallet';
            $typeOut = 7;
            $typeIn = 17;
            //计算累计收益
            $data['cum_returns'] = 0;
            $data['transfer_amount'] = $req['butie_amount'];
            $data['user_id'] = $user['id'];
            $data['type'] = 2;
            $data['status'] = 3;
            $data['from_wallet'] = $fieldOut;
            $data['add_time'] = date('Y-m-d H:i:s',time());

            // 创建订单
            $order = OrderTransfer::create($data);

            
            // 扣除
            User::changeInc($user['id'],-$req['butie_amount'],$fieldOut,57,$order['id'],$typeOut,"幸福收益转出",0,1);
            // 新增
            User::changeInc($user['id'],$req['butie_amount'],$fieldIn,57,$order['id'],$typeIn,"幸福收益转出",0,1);
                


            Db::commit();
            return out([], 0, '转出成功');
        } catch (\Exception $e) {
            Db::rollback();
            return out([], 1, '转出失败：' . $e->getMessage());
        }
    }
    
    public function getTransferList(){
        $user = $this->user;
        $typeArr = [1=>'',2=>''];
        $fromWalletArr = ['appreciating_wallet'=>'收益转出','butie'=>'共富金转入','butie_lock'=>'共富金自动转出','digit_balance'=>'收益','fenhong_digit_balance'=>'月度分红','gongfu_wallet'=>'共富金转入'];
        $statusArr = [1=>'进行中',2=>'提前转出',3=>'已完成'];
        $orders = OrderTransfer::where('user_id', $user['id'])->paginate();
        foreach($orders as $key=>$value){
            $orders[$key]['type_name'] = $typeArr[$value['type']];
            $orders[$key]['from_wallet_name'] = $value['from_wallet'] ? $fromWalletArr[$value['from_wallet']] : '';
            $orders[$key]['status_name'] = $value['status'] ? $statusArr[$value['status']] : '';
        }
        return out($orders);

    }

    public function xingfuZhongGuo(){
        $user = $this->user;
        $user = User::where('id', $user['id'])->lock(true)->find();
        $orders = OrderTransfer::where('user_id', $user['id'])->where('status','<>',2)->where('type',1)->where('from_wallet','butie')->order('id','desc')->find();
        $count = OrderTransfer::where('user_id', $user['id'])->where('type', 1)->where('status','<>',2)
            ->where('from_wallet','in',['butie','gongfu_wallet'])
            ->fieldRaw('SUM(cum_returns) as cum_returns_sum, SUM(transfer_amount) as transfer_amount_sum')
            ->find();
        $fenhong = UserBalanceLog::where('user_id',$user['id'])->where('type',64)->where('status',1)->field('SUM(change_balance) as amount_sum')->find();
        
        $startDate = date('Y-m-01 00:00:00');
        $endDate = date('Y-m-01 00:00:00', strtotime('+1 month'));
        // $money = dbconfig('monthly_fenhong_amount');
        $benci_shoyi = userBalanceLog::where([
            ['type', '=', 64],
            ['created_at', '>=', $startDate],
            ['created_at', '<', $endDate],
            ['status', '=', 1],
            ['user_id', '=', $user['id']],
        ])->find();
        $benci_shoyi = isset($benci_shoyi['change_balance']) ? $benci_shoyi['change_balance'] : 0;
        $date['zong_jinr'] = $user['butie_lock'];
        $date['leiji_zhuanru'] = $count['transfer_amount_sum'] ?? 0;
        $date['benci_shoyi'] = round($benci_shoyi,2) ?? 0;
        $date['leiji_shouyi'] = $count['cum_returns_sum'] ?? 0;
        $date['zong_fenhong'] = $fenhong['amount_sum'] ?? 0;
        $date['order_info'] = $orders;
        return out($date);
    }

}

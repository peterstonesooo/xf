<?php

namespace app\model;

use Exception;
use think\Model;
use think\facade\Db;
use think\facade\Cache;

class User extends Model
{
    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.user')['status_map'];
        return $map[$data['status']];
    }

    public function getLevelTextAttr($value, $data)
    {
        $map = config('map.user')['level_map'];
        return $map[$data['level']];
    }

    public function getShimingStatusTextAttr($value, $data)
    {
        $map = array(
            0 => '未实名',
            1 => '已实名',
        );
        return $map[$data['shiming_status']];
    }

    public function getIsActiveTextAttr($value, $data)
    {
        $map = config('map.user')['is_active_map'];
        return $map[$data['is_active']];
    }

    public function getActiveDateAttr($value, $data)
    {
        if (!empty($data['active_time'])) {
            return date('Y-m-d H:i:s', $data['active_time']);
        }
        return '';
    }



    public function upUser()
    {
        return $this->belongsTo(User::class, 'up_user_id');
    }

    // 持有股权
    public function getEquityAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(EquityYuanRecord::where('user_id', $data['id'])->where('status', 2)->where('type', 1)->sum('num'), 2);
        }
        return 0;
    }

    //推荐奖励总计
    public function getInviteBonus($value,$data){
        if (!empty($data['id'])) {
           // return round(UserBalanceLog::where('user_id', $data['id'])->where('type', 9)->sum('change_balance'), 2);
           return $data['invite_bonus'];
        }
        return 0;
    }

    // 持有数字人民币
    public function getDigitalYuanAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(EquityYuanRecord::where('user_id', $data['id'])->where('status', 2)->where('type', 2)->sum('num'), 2);
        }
        return 0;
    }
    // 持有贫困补助金
/*     public function getPovertySubsidyAmountAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(EquityYuanRecord::where('user_id', $data['id'])->where('status', 2)->where('type', 3)->sum('num'), 2);
        }
        return 0;
    }
 */
    // 我的分红
    public function getMyBonusAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(Order::where('user_id', $data['id'])->whereIn('status', [2,3,4])->sum('gain_bonus'), 2);
        }
        return 0;
    }

    // 累计总分红
    public function getTotalBonusAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(Order::where('user_id', $data['id'])->where('status', 4)->sum('gain_bonus'), 2);
        }
        return 0;
    }

    // 分红收益中
    public function getProfitingBonusAttr($value, $data)
    {
        if (!empty($data['id'])) {
            //return round(Order::where('user_id', $data['id'])->whereIn('status', [2,3,4])->sum('gain_bonus'), 2);
            $money1= Order::where('user_id', $data['id'])->whereIn('status', [2,3,4])->sum('sum_amount2');
            $money2= Order::where('user_id', $data['id'])->whereIn('status', [2,3,4])->sum('sum_amount');
            return round($money1+$money2,2);
        }
        return 0;
    }

    // 已兑换股权
    public function getExchangeEquityAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(EquityYuanRecord::where('user_id', $data['id'])->where('status', 3)->where('type', 1)->sum('num'), 2);
        }
        return 0;
    }

    // 已兑换期权
    public function getExchangeDigitalYuanAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(EquityYuanRecord::where('user_id', $data['id'])->where('status', 3)->where('type', 2)->sum('num'), 2);
        }
        return 0;
    }

    // 总被动收益
    public function getPassiveTotalIncomeAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(PassiveIncomeRecord::where('user_id', $data['id'])->whereIn('status', [2, 3])->sum('amount'), 2);
        }
        return 0;
    }
    
    // 总补贴
    public function getSubsidyTotalIncomeAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(SubsidyIncomeRecord::where('user_id', $data['id'])->whereIn('status', [2, 3])->sum('amount'), 2);
        }
        return 0;
    }
    
    // 已领取的被动收益
    public function getPassiveReceiveIncomeAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(PassiveIncomeRecord::where('user_id', $data['id'])->where('status', 3)->sum('amount'), 2);
        }
        return 0;
    }

    // 未领取的被动收益
    public function getPassiveWaitIncomeAttr($value, $data)
    {
        if (!empty($data['id'])) {
            return round(PassiveIncomeRecord::where('user_id', $data['id'])->where('status', 2)->sum('amount'), 2);
        }
        return 0;
    }

    // 团队人数
    public function getTeamUserNumAttr($value, $data)
    {
        return UserRelation::where('user_id', $data['id'])->count();
    }

    // 团队业绩
    public function getTeamPerformanceAttr($value, $data)
    {
    	$sub_user_ids = UserRelation::where('user_id', $data['id'])->column('sub_user_id');

        if (!empty($sub_user_ids)) {
            //$sql = "select sum(invest_amount) as invest_amount from mp_user where  id in (".join(',', $sub_user_ids).")";
            $sql = "select sum(a.invest_amount)as invest_amount from mp_user as a inner join mp_user_relation as b on a.id=b.sub_user_id where b.user_id=".$data['id'];
            $users = Db::query($sql);
            return round($users[0]['invest_amount'], 2);
        }
        return 0;
    }
// 团队业绩
//    public function getTeamPerformanceAttr($value, $data)
//    {
//        $sub_user_ids = UserRelation::where('user_id', $data['id'])->column('sub_user_id');

//        if (!empty($sub_user_ids)) {
//            $sql = "select sum(a.invest_amount)as invest_amount from mp_user as a inner join mp_user_relation as b on a.id=b.sub_user_id where b.user_id=".$data['id'];
//            $users = Db::query($sql);
//            return round($users[0]['invest_amount'], 2);
//        }
//        return 0;
//	}
    // 用户最大连续签到天数
    public function getMaxContinuitySigninNumAttr($value, $data)
    {
        $signin_dates = UserSignin::where('user_id', $data['id'])->order('id', 'asc')->column('signin_date');
        if (!empty($signin_dates)) {
            return continue_days($signin_dates);
        }
        return 0;
    }

    // 用户充值总金额
    public function getTotalTopupAmountAttr($value, $data)
    {
        return round(Capital::where('user_id', $data['id'])->where('status', 2)->where('type', 1)->sum('amount'), 2);
    }

    // 用户总入金金额
    public function getTotalDepositAmountAttr($value, $data)
    {
        return round(UserBalanceLog::where('user_id', $data['id'])->where('log_type', 1)->whereIn('type', [1,15])->sum('change_balance'), 2);
    }

    // 用户支付总金额 包括后台入金
    public function getTotalPaymentAmountAttr($value, $data)
    {
        $amount1 = Payment::where('user_id', $data['id'])->where('status', 2)->sum('pay_amount');
        $amount2 = UserBalanceLog::where('user_id', $data['id'])->where('log_type', 1)->where('type', 15)->sum('change_balance');
        return round($amount1 + $amount2, 2);
    }

    /**
     * 方法1：获取用户五福（project_group_id=7,8,9,10,11）购买金额
     * - 同时统计 mp_order 与 mp_order_daily_bonus
     * - 仅统计已支付订单：status >= 2
     */
    public static function getUserWufuBuyAmount(int $userId): float
    {
        $groupIds = [7, 8, 9, 10, 11];
        $excludeProjectIds = [187,188,189,190,191, 192, 193,194]; // 春来福至，不计入五福购买金额

        $orderAmount = (float)Order::alias('o')
            ->join('project p', 'o.project_id = p.id')
            ->where('o.user_id', $userId)
            ->where('o.status', '>=', 2)
            ->where('p.project_group_id', 'in', $groupIds)
            ->whereNotIn('o.project_id', $excludeProjectIds)
            ->sum(Db::raw('IFNULL(o.buy_num,1) * IFNULL(o.single_amount,0)'));

        $dailyBonusAmount = (float)OrderDailyBonus::alias('o')
            ->join('project p', 'o.project_id = p.id')
            ->where('o.user_id', $userId)
            ->where('o.status', '>=', 2)
            ->where('p.project_group_id', 'in', $groupIds)
            ->whereNotIn('o.project_id', $excludeProjectIds)
            ->sum(Db::raw('IFNULL(o.buy_num,1) * IFNULL(o.single_amount,0)'));

        return round($orderAmount + $dailyBonusAmount, 2);
    }

    /**
     * 方法2：计算同心同行用户购买的金额（mp_order_tongxing）
     * - 仅统计已支付订单：status >= 2
     */
    public static function getUserTongxingBuyAmount(int $userId): float
    {
        $amount = (float)OrderTongxing::where('user_id', $userId)
            ->where('status', '>=', 2)
            ->sum('price');

        return round($amount, 2);
    }

    /**
     * 方法3：计算用户幸福权益花费的金额
     * - 以 happiness_equity_activation.payment_amount 汇总
     */
    public static function getUserHappinessEquitySpendAmount(int $userId): float
    {
        $amount = (float)HappinessEquityActivation::where('user_id', $userId)
            ->sum('payment_amount');

        return round($amount, 2);
    }

    /**
     * 方法4：计算财政拨付花费的金额
     * - 以购买财政拨付项目为口径：project_id in [187,188,189,190]
     * - 仅统计已支付订单：status >= 2
     */
    public static function getUserFiscalSpendAmount(int $userId): float
    {
        $amount = (float)Order::where('user_id', $userId)
            ->where('status', '>=', 2)
            ->where('project_id', 'in', [187, 188, 189, 190])
            ->sum(Db::raw('IFNULL(buy_num,1) * IFNULL(single_amount,0)'));

        return round($amount, 2);
    }

    /**
     * 方法5：计算信息对接话费的金额
     * - 以 PeopleLivelihoodInfo 已完成记录为口径（total_payment > 0）
     */
    public static function getUserInfoDockingPhoneFeeAmount(int $userId): float
    {
        $amount = (float)PeopleLivelihoodInfo::where('payment_user_id', $userId)
            ->where('total_payment', '>', 0)
            ->sum('total_payment');

        return round($amount, 2);
    }

    /**
     * 方法6：计算春来福至话费的金额
     * - 以购买项目 191/192/193 的订单金额为口径
     * - 仅统计已支付订单：status >= 2
     */
    public static function getUserChunlaiFuzhiPhoneFeeAmount(int $userId): float
    {
        $amount = (float)Order::where('user_id', $userId)
            ->where('status', '>=', 2)
            ->where('project_id', 'in', [191, 192, 193])
            ->sum(Db::raw('IFNULL(buy_num,1) * IFNULL(single_amount,0)'));

        return round($amount, 2);
    }

    /**
     * 方法7：计算购买VIP花费的金额
     * - 以 vip_log.pay_amount 为口径（status=1 表示成功）
     */
    public static function getUserVipSpendAmount(int $userId): float
    {
        $vipStatus = (int)self::where('id', $userId)->value('vip_status');
        return $vipStatus === 1 ? 8500.0 : 0.0;
    }

    // 用户可提现余额
    public function getCanWithdrawBalanceAttr($value, $data)
    {
        return round($data['balance'] - $data['topup_balance'], 2);
    }

    // 获取直属下级已实名的人数
    public function getRealSubUserNumAttr($value, $data)
    {
        return User::where('up_user_id', $data['id'])->where('shiming_status', 1)->count();
    }

    // 获取团队下级人数
    public function getSubUserNumAttr($value, $data)
    {
        $sub_user_ids = UserRelation::where('user_id', $data['id'])->column('sub_user_id');
        if (!empty($sub_user_ids)) {
            return User::whereIn('id', $sub_user_ids)->count();
        }
        return 0;
    }

    // 获取今日直属下级实名人数
    public function getRealSubUserNumTodayAttr($value, $data)
    {
        return User::where('up_user_id', $data['id'])->where('shiming_status', 1)->where('created_at', '>=', date('Y-m-d 00:00:00'))->where('created_at', '<=', date('Y-m-d 23:59:59'))->count();
    }

    // 获取团队封禁状态 - 如果下级有启用的用户，就显示启用状态
    public function getTeamBanStatusAttr($value, $data)
    {
        if (empty($data['id'])) {
            return 0;
        }
        
        // 获取用户的所有下级用户ID
        $subUserIds = UserRelation::where('user_id', $data['id'])->where('level','in', [1,2,3])->column('sub_user_id');
        
        if (!empty($subUserIds)) {
            // 检查是否有下级用户是启用状态（status = 1）
            $activeSubUsers = User::whereIn('id', $subUserIds)->where('status', 1)->count();
            return $activeSubUsers > 0 ? 1 : 0;
        }
        
        return 0;
    }

    // 获取幸福权益状态文本
    public function getHappinessEquityStatusTextAttr($value, $data)
    {
        if (empty($data['id'])) {
            return '未激活';
        }
        
        // 检查用户是否已激活幸福权益
        $activation = HappinessEquityActivation::where('user_id', $data['id'])
                                              ->where('status', 1)
                                              ->find();
        
        return $activation ? '已激活' : '未激活';
    }

    //通过token获取用户信息
    public static function getUserByToken($is_exit = true)
    {
        if ($token = request()->header('token')) {
            $arr = aes_decrypt($token);
            if (!empty($arr['id'])) {
                $user = User::where('id', $arr['id'])->find();
                if (empty($user)){
                    exit_out(null, 601, '账号不存在');
                }
                if ($user['status'] == 0){
                    exit_out(null, 602, '账号已被冻结');
                }

                return $user;
            }
        }

        if ($is_exit) {
            exit_out(null, 401, '认证失效，请重新登录');
        }

        return [];
    }

    // 改变用户余额或积分,加$change_balance为正数 ，减$change_balance为负数
    public static function changeBalance($user_id, $change_balance, $type, $relation_id = 0, $log_type = 1, $remark = '', $admin_user_id = 0)
    {
        if (!empty($change_balance) && $change_balance != 0) {
            $user = User::where('id', $user_id)->find();

            //$field = $log_type == 1 ? 'balance' : 'integral';
            if ($log_type == 1){ 
                if($type == 3){
                    //购买商品
                    $field = 'topup_balance';
                }else{
                    $field = 'balance';
                }
                
            }elseif($log_type == 2){
                $field = 'integral';
            }elseif($log_type == 3){
                $field = 'digital_yuan_amount';
            }elseif($log_type == 4){
                $field = 'poverty_subsidy_amount';
            }elseif($log_type == 5){
                $field = 'topup_balance';
            }else{
                $field = 'balance';
            }
            if($type == 3 && $log_type != 3){
                $before_balance = $user['topup_balance'] + $user['balance'];
                $after_balance = $before_balance + $change_balance;
                if ($after_balance < 0) {
                    $after_balance = 0;
                    $change_balance = 0 - $before_balance;
                }
            }else{
                $before_balance = $user[$field];
                $after_balance = $user[$field] + $change_balance;
                if ($after_balance < 0) {
                    $after_balance = 0;
                    $change_balance = 0 - $user[$field];
                }
            }

            // 如果是充值 就要添加充值金额
            if ($log_type == 1 && in_array($type, [1, 15])) {
                //User::where('id', $user_id)->inc($field, $change_balance)->inc('topup_balance', $change_balance)->update();
                User::where('id', $user_id)->inc('topup_balance', $change_balance)->update();
            }
            // 如果是购买项目，优先扣充值余额里面的钱
            elseif ($log_type == 1 && $type == 3) {
                $real_balance = 0 - $change_balance;
                $change_topup_balance = $user['topup_balance'] > $real_balance ? $real_balance : $user['topup_balance'];
                $change_topup_balance = 0 - $change_topup_balance;
                $sub_balance = $user['topup_balance'] < $real_balance ? ($change_balance + $user['topup_balance']) : 0;
                User::where('id', $user_id)->inc('balance', $sub_balance)->inc('topup_balance', $change_topup_balance)->update();
            }
            // 手动出金 先扣可提现金额，再扣充值金额
            elseif ($log_type == 1 && $type == 16) {
                $real_balance = 0 - $change_balance;
                if ($real_balance > $user['can_withdraw_balance']) {
                    $real_topup_balance = $real_balance - $user['can_withdraw_balance'];
                    $real_topup_balance = 0 - $real_topup_balance;
                    User::where('id', $user_id)->inc($field, $change_balance)->inc('topup_balance', $real_topup_balance)->update();
                }
                else {
                    User::where('id', $user_id)->inc($field, $change_balance)->update();
                }
            }
            else {
                User::where('id', $user_id)->inc($field, $change_balance)->update();
            }

            $status = $type == 2 ? 1 : 2;
            if ($type == 13) {
                $status = 3;
            }
            UserBalanceLog::create([
                'user_id' => $user_id,
                'type' => $type,
                'log_type' => $log_type,
                'relation_id' => $relation_id,
                'before_balance' => $before_balance,
                'change_balance' => $change_balance,
                'after_balance' => $after_balance,
                'remark' => $remark,
                'admin_user_id' => $admin_user_id,
                'status' => $status,
            ]);

            // 判断用户升级逻辑
           //shanchu
        }

        return true;
    }

    public static function changeInc($user_id,$amount,$field,$type,$relation_id = 0,$log_type = 1, $remark = '',$admin_user_id=0,$status=1,$sn_prefix='MR',$is_delete=0){
        $user = User::where('id', $user_id)->find();


        $after_balance = $user[$field]+$amount;
        if($amount<0 && $user[$field]<abs($amount)){
            throw new Exception('余额不足');
        }

        
        Db::startTrans();
        try {
            $ret = User::where('id',$user_id)->inc($field,$amount)->update();
            $sn = build_order_sn($user_id,$sn_prefix);
            UserBalanceLog::create([
                'user_id' => $user_id,
                'type' => $type,
                'log_type' => $log_type,
                'relation_id' => $relation_id,
                'before_balance' => $user[$field],
                'change_balance' => $amount,
                'after_balance' => $after_balance,
                'remark' => $remark,
                'admin_user_id' =>$admin_user_id,
                'status' => $status,
                'order_sn'=>$sn,
                'is_delete'=>$is_delete,
            ]);
            Db::commit();
            return 'success';
        }catch(\Exception $e){
            Db::rollback();
            //return $e->getMessage();
            throw $e;
        }
    }

    public static function changeInc1($user_id,$amount,$field,$type,$relation_id = 0,$log_type = 1, $remark = '',$admin_user_id=0,$status=1,$sn_prefix='MR'){
        $user = User::where('id', $user_id)->find();

        $after_balance = $user[$field]+$amount;
//        if($amount<0 && $user[$field]<abs($amount)){
//            throw new Exception('余额不足');
//        }

        Db::startTrans();
        try {
            $ret = User::where('id',$user_id)->inc($field,$amount)->update();
            $sn = build_order_sn($user_id,$sn_prefix);
            UserBalanceLog::create([
                'user_id' => $user_id,
                'type' => $type,
                'log_type' => $log_type,
                'relation_id' => $relation_id,
                'before_balance' => $user[$field],
                'change_balance' => $amount,
                'after_balance' => $after_balance,
                'remark' => $remark,
                'admin_user_id' =>$admin_user_id,
                'status' => $status,
                'order_sn'=>$sn,
            ]);
            Db::commit();
            return 'success';
        }catch(\Exception $e){
            Db::rollback();
            //return $e->getMessage();
            throw $e;
        }
    }

    // 获取用户的上3级的用户id
    public static function getThreeUpUserId($user_id)
    {
        $level = [];
        $user = User::field('id,up_user_id')->where('id', $user_id)->find();
        if (!empty($user['up_user_id'])) {
            $upUser1 = User::field('id,up_user_id')->where('id', $user['up_user_id'])->find();
            $level[1] = $upUser1['id'];
            if (!empty($upUser1['up_user_id'])) {
                $level[2] = $upUser1['up_user_id'];
                $upUser2 = User::field('id,up_user_id')->where('id', $upUser1['up_user_id'])->find();
                if (!empty($upUser2['up_user_id'])) {
                    $level[3] = $upUser2['up_user_id'];
                } 
            }
        }

        return $level;
    }

    // 获取用户的所有上级用户id
    public static function getAllUpUserId($user_id)
    {
        $level = [];
        $current_user = User::field('id,up_user_id')->where('id', $user_id)->find();
        $i = 1;
        
        while (!empty($current_user['up_user_id'])) {
            $up_user = User::field('id,up_user_id')->where('id', $current_user['up_user_id'])->find();
            if (empty($up_user)) {
                break;
            }
            
            $level[$i] = $up_user['id'];

            if ($i >= 5) {
                break;
            }

            $current_user = $up_user;
            $i++;
        }
        
        return $level;
    }

    public function teamBonus($user_id,$amount,$paymentId){
        return true;
        //总充值金额
        //if ($order['pay_method'] != 5) {
        //} 
        $user = User::where('id',$user_id)->field('id,up_user_id,invest_amount,level')->find();
        if(empty($user)){
            throw new Exception('用户不存在');
        }

        $upUser = User::where('id',$user['up_user_id'])->field('level')->find();
        if(empty($upUser)){
            return false;
        }
               // 如果不是积分兑换才算直推奖励和团队奖励
               //if ($order['pay_method'] != 5 && !empty($up_user_id)) {
                // 给直属上级推荐奖
                $levelConfig = LevelConfig::where('level', $upUser['level'])->find();
/*                 if (!empty($levelConfig['direct_recommend_reward_ratio'])) {
                    $reward = round($levelConfig['direct_recommend_reward_ratio']/100*$amount, 2);
                    if($reward > 0){
                        //User::changeBalance($up_user_id, $reward, 9, $order_id);
                        User::changeInc($user['up_user_id'],$reward,'team_bonus_balance',9,$paymentId,2,'推荐奖励',0,2);
                        //User::changeInc($up_user_id,$reward,'balance',9,$order_id,1,'推荐奖励',0,2);
                    }
                } */
                // 给上3级团队奖
                $relation = UserRelation::where('sub_user_id', $user_id)->select();
                $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
                //$map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', ];
                foreach ($relation as $v) {
                    $reward = round(dbconfig($map[$v['level']])/100*$amount, 2);
                    if($reward > 0){
                        //User::changeBalance($v['user_id'], $reward, 8, $order_id);
                        //User::changeInc($up_user_id,$reward,'invite_bonus',8,$order_id,3,'推荐奖励');
                        User::changeInc($v['user_id'],$reward,'team_bonus_balance',8,$paymentId,2,'团队奖励',0,2,'TD');
                        //User::changeInc($v['user_id'],$reward,'balance',8,$order_id,1,'团队奖励',0,2);
                    }
                }
            //}
    
            // 检测用户升级
            //if (in_array($order['pay_method'], [1,2,3,4,6])) {
            //if (in_array($order['pay_method'], [2, 3, 4, 6])) {
                //$user = User::where('id', $order['user_id'])->find();

            //}
    }

    public static function upLevel($user_id){
                // 检测用户升级
            //User::where('id', $user_id)->inc('invest_amount', $amount)->update();
/*             $orderSum = Order::where('user_id',$user_id)->where('status','>=',2)->sum('single_amount');
            $assetOrderSum = UserBalanceLog::where('user_id',$user_id)->where('type',25)->sum('change_balance');
 */         $user = User::where('id',$user_id)->field('invest_amount,level,is_active')->find();

            // $new_level = LevelConfig::where('min_topup_amount', '<=', intval($user['invest_amount']))->order('min_topup_amount', 'desc')->value('level');
            // $user = User::where('id',$user_id)->field('level')->find();
            // if ($user['level'] < $new_level) {
            //     //User::where('id', $user['id'])->update(['level' => $new_level]);
            // }
//            $newAmount = $user['invest_amount'];
//            if ($newAmount >= 100 && $newAmount <= 1000) {
//                $level = 1;
//            } elseif ($newAmount >= 1001 && $newAmount <= 3000) {
//                $level = 2;
//            } elseif ($newAmount >= 3001 && $newAmount <= 6000) {
//                $level = 3;
//            } elseif ($newAmount >= 6001) {
//                $level = 4;
//            } else {
//                $level = 0;
//            }
            //获取团队人数,实名人数
            $zong_total = UserRelation::alias('ur')->leftJoin('mp_user u','ur.sub_user_id=u.id')
            ->where('ur.user_id', $user_id)
            ->where('u.shiming_status',1)
            ->where('ur.level','in', [1,2,3])
            ->count(); //实名团队人
            
            if(!Cache::get('teamGlorySetting')){
                Cache::set('teamGlorySetting',TeamGlorySetting::select()->order('level','asc')->toArray(),300);
            }
            $teamGlorySetting = Cache::get('teamGlorySetting');
            $levelPoints[0] = 0;
            foreach ($teamGlorySetting as $k => $v){
                $levelPoints[$v['level']] = $v['register_num'];
            }

            //查看所属层级
            $level = self::getVipLevel($zong_total,$levelPoints);
//            echo $user['level'];
//            echo '---';
//        echo $level;exit;
            if ($user['level'] < $level) {
                User::where('id', $user_id)->update(['level' => $level]);
            }
            if ($user['is_active'] == 0) {
                // $userUpdate['is_active'] = 1;
                // $userUpdate['active_time'] = time();
                // // 下级用户激活
                // \app\model\UserRelation::where('sub_user_id', $user_id)->update(['is_active' => 1]);
                // User::where('id', $user_id)->update($userUpdate);
            }
            
    }

    public static function getVipLevel($levelNum, $config) {
        $lastLevel = null;
        foreach ($config as $level => $threshold) {
            if ($levelNum >= $threshold) {
                $lastLevel = $level;
            } else {
                break;
            }
        }
        return $lastLevel;
    }

    public static function upLevela($user_id){
                // 检测用户升级
            //User::where('id', $user_id)->inc('invest_amount', $amount)->update();
/*             $orderSum = Order::where('user_id',$user_id)->where('status','>=',2)->sum('single_amount');
            $assetOrderSum = UserBalanceLog::where('user_id',$user_id)->where('type',25)->sum('change_balance');
 */         $user = User::where('id',$user_id)->field('invest_amount,level,is_active')->find();

            // $new_level = LevelConfig::where('min_topup_amount', '<=', intval($user['invest_amount']))->order('min_topup_amount', 'desc')->value('level');
            // $user = User::where('id',$user_id)->field('level')->find();
            // if ($user['level'] < $new_level) {
            //     //User::where('id', $user['id'])->update(['level' => $new_level]);
            // }
            $newAmount = $user['invest_amount'];
            if ($newAmount >= 100 && $newAmount <= 1000) {
                $level = 1;
            } elseif ($newAmount >= 1001 && $newAmount <= 3000) {
                $level = 2;
            } elseif ($newAmount >= 3001 && $newAmount <= 6000) {
                $level = 3;
            } elseif ($newAmount >= 6001) {
                $level = 4;
            } else {
                $level = 0;
            }

            if ($user['level'] < $level) {
                User::where('id', $user_id)->update(['level' => $level]);
            }
            if($level == 0) {
                // $userUpdate['is_active'] = 0;
                // $userUpdate['active_time'] = 0;
                // // 下级用户激活
                // \app\model\UserRelation::where('sub_user_id', $user_id)->update(['is_active' => 0]);
                // User::where('id', $user_id)->update($userUpdate);
            }
            // if ($user['is_active'] == 0) {
            //     $userUpdate['is_active'] = 1;
            //     $userUpdate['active_time'] = time();
            //     // 下级用户激活
            //     \app\model\UserRelation::where('sub_user_id', $user_id)->update(['is_active' => 1]);
            //     User::where('id', $user_id)->update($userUpdate);
            // }
            
    }

    public static function isThreeStage($userId){
        $order1 = Order::where('user_id',$userId)->where('status','>=',2)->where('project_group_id',1)->find();
        if(!$order1){
            return 0;
        }
        $order2 = Order::where('user_id',$userId)->where('status','>=',2)->where('project_group_id',2)->find();
        if(!$order2){
            return 0;
        }
        $order3 = Order::where('user_id',$userId)->where('status','>=',2)->where('project_group_id',3)->find();
        if(!$order3){
            return 0;
        }
        return 1;
    }

    public static function myHouse($userId){
        $apply = Apply::where('user_id',$userId)->where('type',2)->find();
        if(!$apply){
            return ['msg'=>'请先预约看房'];
        }
        $order = Order::where('user_id',$userId)->where('project_group_id',3)->where('status','>=',2)->select();
        $priceMax = [];
        foreach($order as $item){
            if($priceMax==[]){
                $priceMax = $item;
            }
            if($priceMax && $item['single_amount']>$priceMax['single_amount']){
                $priceMax = $item;
            }
        }
        if(empty($priceMax)){
            return ['msg'=>'请先购买房产'];
        }
        return ['msg'=>'','house'=>$priceMax];
    }

    public static function isCardOrder($userId){
        $order = Order::where('user_id',$userId)->where('project_group_id',5)->where('status','>=',2)->find();
        if(!$order){
            return 0;
        }
        return 1;
    }

    public static function cardWithdrawSum($userId){
        $user = User::where('id',$userId)->find();
        $alreayCount = Capital::where('user_id',$userId)->where('type',2)->whereIn('log_type',[3,6])->sum('amount');
        $sum = abs($alreayCount)+$user['digital_yuan_amount']+$user['income_balance'];
        return $sum;
    }

    public static function cardRecommend($sum){
        $conf = config('map.project.project_card');
        foreach($conf as $k=>$v){
            if($sum>=$v['min'] && $sum<$v['max']){
                return $k;
            }
        }
    }

    public static function walletSn($userId){
        $sn = '';
        $wallet = Db::table('mp_wallet')->where('user_id',$userId)->find();
        if(!$wallet){
            $sn = self::generateRandomNumber($userId);
            Db::table('mp_wallet')->insert(['user_id'=>$userId,'sn'=>$sn]);
        }
        $sn = $wallet['sn'];
        $arr = explode(' ',$sn);
        //$arr[1] = $arr[2] = "****";
        $sn1 = implode(' ',$arr);
        return $sn1;

    }

    public static function generateRandomNumber($userId) {
        $part1 = '1'. str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
        $part2 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        //$part2 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $part3 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $part4 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
        $randomNumber = $part1 . ' ' . $part2 . ' ' . $part3 . ' ' . $part4;
    
        return $randomNumber;
    }

    //测试用户数组
    public static function testUser()
    {
        return [
            '13911139111',
            //'19954017811',
            '17191119859',
            '13018952330',
            '16521947228',
            '18577446091',
            '17825266444',
            '13222847746',
            '13195762584',
            '18346834846',
            '13846835677',
            '15586298850',
            '13697886988',
            '18395814846',
            '17834551237',
            '13921544841',
        ];
    }

    public static function allAmount($user)
    {
        //获取用户钱包的总额
//        return floatval($user['invite_bonus']) + floatval($user['digital_yuan_amount']) + floatval($user['income_balance']);
        return floatval(
            $user['topup_balance']) + floatval($user['income_balance']) + floatval($user['shengyu_balance'] + $user['xuanchuan_balance'] + floatval($user['shengyu_butie_balance'])
            );
    }

    //办卡三级返佣
    public static function buyRewardV3($userId, $userCardId)
    {
        $userCard = UserCardRecord::where('id', $userCardId)->find();
        $user = User::field('id, phone')->where('id', $userId)->find();
        if (!$userCard || !$user) {
            return false;
        }
        $ids = self::getThreeUpUserId($userId);
        $rate = [1 => 0.15, 2 => 0.1, 3 => 0.05];
        foreach ($ids as $k => $id) {
            $amount = floatval($userCard['fee']) * $rate[$k];
            self::changeIncs($id, $amount, 'balance', 46, $userCardId, 6, '用户' . $user['phone'] . '生育卡预存上级分成奖励');
        }
        return true;
    }

    // 获取所有下级用户ID
    public function getAllSubUserIdsAttr($value, $data)
    {
        if (empty($data['id'])) {
            return [];
        }
        
        $subUserIds = [];
        $this->getSubUserIds($data['id'], $subUserIds);
        return $subUserIds;
    }

    // 递归获取下级用户ID
    private function getSubUserIds($userId, &$subUserIds)
    {
        $subUsers = User::where('up_user_id', $userId)->column('id');
        if (!empty($subUsers)) {
            $subUserIds = array_merge($subUserIds, $subUsers);
            foreach ($subUsers as $subUserId) {
                $this->getSubUserIds($subUserId, $subUserIds);
            }
        }
    }
}

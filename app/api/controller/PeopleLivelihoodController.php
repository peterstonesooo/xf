<?php

namespace app\api\controller;

use app\model\User;
use app\model\UserGoldWallet;
use app\model\HappinessEquityActivation;
use app\model\ExclusiveLog;
use app\model\LoanApplication;
use app\model\Order;
use app\model\OrderDailyBonus;
use app\model\OrderTongxing;
use app\model\OrderDingtou;
use app\model\OrderTiyan;
use app\model\PeopleLivelihoodConfig;
use app\model\PeopleLivelihoodInfo;
use app\model\Capital;
use app\model\TeamGloryLog;
use app\model\UserRelation;
use app\model\RelationshipRewardLog;
use think\facade\Db;
use Exception;

class PeopleLivelihoodController extends AuthController
{
    /**
     * 查询用户信息（民生信息对接中心）
     * 接收参数：is_self（是否本人：1-是，2-否）, user_name（姓名）, ic_card（身份证号）
     */
    public function getUserInfo()
    {
        try {
            $req = $this->validate(request(), [
                'is_self|是否本人' => 'require|number|in:1,2',
                'user_name|姓名' => 'require',
                'ic_card|身份证号' => 'require',
            ]);

            // 根据姓名和身份证号查找用户
            $user = User::where('realname', $req['user_name'])
                       ->where('ic_number', $req['ic_card'])
                       ->find();

            if (!$user) {
                return out(null, 10001, '未找到该用户信息');
            }

            // 如果是本人查询，需要验证是否为当前登录用户
            if ($req['is_self'] == 1) {
                if ($user['id'] != $this->user['id']) {
                    return out(null, 10001, '身份验证失败，不是本人');
                }
            }

            // 获取用户黄金钱包
            $goldWallet = UserGoldWallet::where('user_id', $user['id'])->find();
            $goldBalance = $goldWallet ? $goldWallet['gold_balance'] : 0;

            // 获取配置信息（只返回启用的配置）
            $configs = PeopleLivelihoodConfig::getEnabledConfigs();

            // 查询每个钱包的待审核提现金额（一次查询优化）
            // log_type: 4=民生钱包, 5=惠民钱包, 3=稳盈钱包, 14=振兴钱包, 16=共富钱包, 17=收益钱包, 21=综合钱包
            // type=2表示提现, status=1表示待审核
            $pendingWithdraws = Capital::where('user_id', $user['id'])
                ->where('type', 2)
                ->where('status', 1)
                ->whereIn('log_type', [4, 5, 3, 14, 16, 17,21])
                ->field('log_type, sum(abs(amount)) as total_amount')
                ->group('log_type')
                ->select();
            
            // 初始化各钱包待审核提现金额
            $pendingBalance = 0; // 民生钱包 (log_type=4)
            $pendingDigitBalance = 0; // 惠民钱包 (log_type=5)
            $pendingButie = 0; // 稳盈钱包 (log_type=3)
            $pendingZhenxingWallet = 0; // 振兴钱包 (log_type=14)
            $pendingGongfuWallet = 0; // 共富钱包 (log_type=16)
            $pendingShouyiWallet = 0; // 收益钱包 (log_type=17)
            $pendingZongheWallet = 0; // 综合钱包 (log_type=21)
            
            // 将查询结果按 log_type 分配到对应变量
            foreach ($pendingWithdraws as $item) {
                $logType = $item['log_type'];
                $totalAmount = floatval($item['total_amount'] ?? 0);
                switch ($logType) {
                    case 4:
                        $pendingBalance = $totalAmount;
                        break;
                    case 5:
                        $pendingDigitBalance = $totalAmount;
                        break;
                    case 3:
                        $pendingButie = $totalAmount;
                        break;
                    case 14:
                        $pendingZhenxingWallet = $totalAmount;
                        break;
                    case 16:
                        $pendingGongfuWallet = $totalAmount;
                        break;
                    case 17:
                        $pendingShouyiWallet = $totalAmount;
                        break;
                    case 21:
                        $pendingZongheWallet = $totalAmount;
                        break;
                }
            }

            //计算总计需要缴费（包括待审核提现金额）
            $totalFee = 0;
            $fixedFee = 0;
            $fiscalFundRatio = 0;
            foreach ($configs as $config) {
                if($config['config_key'] == 'fiscal_fund_ratio') {
                    $fiscalFundRatio = $config['config_value'];
                }
                if($config['config_key'] == 'fixed_fee') {
                    $fixedFee = $config['config_value'];
                }
                if($config['config_key'] == 'required_fee') {
                    $requiredFee = $config['config_value'];
                }
            }
            // 钱包余额 + 待审核提现金额
            $walletTotal = bcadd($user['balance'], $pendingBalance, 2);
            $walletTotal = bcadd($walletTotal, $user['digit_balance'], 2);
            $walletTotal = bcadd($walletTotal, $pendingDigitBalance, 2);
            $walletTotal = bcadd($walletTotal, $user['gongfu_wallet'], 2);
            $walletTotal = bcadd($walletTotal, $pendingGongfuWallet, 2);
            $walletTotal = bcadd($walletTotal, $user['zhenxing_wallet'], 2);
            $walletTotal = bcadd($walletTotal, $pendingZhenxingWallet, 2);
            $walletTotal = bcadd($walletTotal, $user['butie'], 2);
            $walletTotal = bcadd($walletTotal, $pendingButie, 2);
            $walletTotal = bcadd($walletTotal, $user['shouyi_wallet'], 2);
            $walletTotal = bcadd($walletTotal, $pendingShouyiWallet, 2);
            $walletTotal = bcadd($walletTotal, $pendingZongheWallet, 2); // 综合钱包待审核提现
            $originalTotalFee = bcmul($walletTotal, bcdiv($fiscalFundRatio, 100, 4), 2);
            $originalTotalFee = bcadd($originalTotalFee, $fixedFee, 2);
            // 使用整数运算避免浮点数精度问题：乘以100转为分
            $totalFeeCents = (int)round((float)$originalTotalFee * 100);
            $totalFee = $totalFeeCents / 100;

            // 计算折扣（参考项目购买逻辑）
            // 获取当前登录用户的折扣信息
            $currentUser = $this->user;
            
            $discount = 1;
            if ($currentUser['vip_status'] == 1) {
                $discount = 0.9;
            }
            // 使用整数运算避免浮点数精度问题：乘以10
            $discountInt = (int)round($discount * 10);
            $discount = $discountInt / 10;
            
            // 计算实际应付金额（应用折扣，使用整数运算）
            $actualTotalFeeCents = (int)round($totalFeeCents * $discountInt / 10);
            $actualTotalFee = $actualTotalFeeCents / 100;

            // 根据身份信息判断mp_people_livelihood_info表是否有数据
            $livelihoodInfo = PeopleLivelihoodInfo::where('payer_user_id', $user['id'])->find();
            
            $paymentStatus = '未缴费'; // 缴费状态
            $fiscalNumber = ''; // 财政编号
            
            if ($livelihoodInfo) {
                // 如果有数据
                if ($livelihoodInfo['total_payment'] == 0) {
                    // total_payment = 0，返回fiscal_number，表示未缴费
                    $fiscalNumber = $livelihoodInfo['fiscal_number'];
                    $paymentStatus = '未缴费';
                } else {
                    // total_payment > 0，表示已缴费
                    $paymentStatus = '已缴费';
                    $user['balance'] = $livelihoodInfo['balance'];
                    $user['digit_balance'] = $livelihoodInfo['digit_balance'];
                    $user['gongfu_wallet'] = $livelihoodInfo['gongfu_wallet'];
                    $user['zhenxing_wallet'] = $livelihoodInfo['zhenxing_wallet'];
                    $user['butie'] = $livelihoodInfo['butie'];
                    $user['shouyi_wallet'] = $livelihoodInfo['shouyi_wallet'];
                    // return out(null, 10002, '已缴费');
                }
            } else {
                // 如果没有数据，生成一条新数据
                // 财政编号格式：姓名首字母大写-年份-邀请码
                $fiscalNumber = $this->generateFiscalNumber($user['realname'], $user['invite_code']);
                
                PeopleLivelihoodInfo::create([
                    'fiscal_number' => $fiscalNumber,
                    'required_fee' => '',
                    'payment_time' => date('Y-m-d H:i:s'),
                    'payer_name' => $req['user_name'],
                    'payer_id_card' => $req['ic_card'],
                    'payer_user_id' => $user['id'],
                    'payment_user_id' => 0,
                    'fiscal_fund_ratio' => 0,
                    'fixed_fee' => 0,
                    'total_payment' => 0,
                ]);
                $paymentStatus = '未缴费';
            }

            // 根据缴费状态决定是否加上待审核提现金额
            // 已缴费：直接使用缴费时保存的钱包余额（不加待审核提现）
            // 未缴费：加上待审核提现金额
            if ($paymentStatus == '已缴费') {
                // 已缴费，直接使用缴费时保存的钱包余额
                $balanceWithPending = $user['balance'];
                $digitBalanceWithPending = $user['digit_balance'];
                $gongfuWalletWithPending = $user['gongfu_wallet'];
                $zhenxingWalletWithPending = $user['zhenxing_wallet'];
                $butieWithPending = $user['butie'];
                $shouyiWalletWithPending = $user['shouyi_wallet'];
                // 已缴费时，综合钱包待审核提现设为0（因为使用缴费时保存的快照）
                $pendingZongheWallet = 0;
            } else {
                // 未缴费，加上待审核提现金额
                $balanceWithPending = bcadd($user['balance'], $pendingBalance, 2);
                $digitBalanceWithPending = bcadd($user['digit_balance'], $pendingDigitBalance, 2);
                $gongfuWalletWithPending = bcadd($user['gongfu_wallet'], $pendingGongfuWallet, 2);
                $zhenxingWalletWithPending = bcadd($user['zhenxing_wallet'], $pendingZhenxingWallet, 2);
                $butieWithPending = bcadd($user['butie'], $pendingButie, 2);
                $shouyiWalletWithPending = bcadd($user['shouyi_wallet'], $pendingShouyiWallet, 2);
                // 未缴费时，使用查询到的综合钱包待审核提现金额（已在上面初始化并赋值）
            }

            // 返回数据
            $data = [
                'user_id' => $user['id'],
                'phone' => $user['phone'],
                'realname' => $user['realname'],
                'ic_number' => $user['ic_number'],
                'register_time' => $user['created_at'], // 注册时间
                'is_shiming' => $user['shiming_status'], // 是否实名：0-未实名，1-已实名
                'minsheng_wallet' => round($balanceWithPending, 2), // 民生钱包余额
                'gongfu_wallet' => round($gongfuWalletWithPending, 2), // 共富钱包
                'huimin_wallet' => round($digitBalanceWithPending, 2), // 惠民钱包
                'zhenxing_wallet' => round($zhenxingWalletWithPending, 2), // 振兴钱包
                'wenyin_wallet' => round($butieWithPending, 2), // 稳盈钱包
                'shouyi_wallet' => round($shouyiWalletWithPending, 2), // 收益钱包
                'other_wallet' => round(bcadd(bcadd(bcadd(bcadd($digitBalanceWithPending, $zhenxingWalletWithPending, 2), $butieWithPending, 2), $shouyiWalletWithPending, 2), $pendingZongheWallet, 2), 2), // 其他钱包（含综合钱包待审核提现）
                'gold_balance' => round($goldBalance, 2), // 持有黄金（克）
                'happiness_equity_count' => $this->getHappinessEquityCount($user['id']), // 幸福堦值累计次数
                'has_subsidy_apply' => $this->hasSubsidyApply($user['id']), // 是否申请专展补贴
                'has_loan_apply' => $this->hasLoanApply($user['id']), // 是否申请惠民货款
                'total_principal' => $this->getTotalPrincipal($user['id']), // 申领本金总额
                'total_fiscal_fund' => $this->getTotalFiscalFund($user['id']), // 已获得财政资金总额
                'configs' => $configs, // 配置信息列表
                'payment_status' => $paymentStatus, // 缴费状态：未缴费/已缴费
                'fiscal_number' => $fiscalNumber, // 财政编号（未缴费时返回）
                'total_fee' => number_format($totalFee, 2, '.', ''), // 总计需要缴费（原始金额，折扣前）- 字符串格式避免JSON精度问题
                'discount' => number_format($discount, 1, '.', ''), // 折扣率（字符串格式避免JSON精度问题）
                'actual_total_fee' => number_format($actualTotalFee, 2, '.', ''), // 实际应付金额（已应用折扣）- 字符串格式避免JSON精度问题
                'fiscal_fund_ratio' => $fiscalFundRatio, // 财政资金比例
                'fixed_fee' => $fixedFee, // 固定费用
                'required_fee' => $requiredFee, // 所需费用
            ];

            return out($data, 0, '查询成功');

        } catch (Exception $e) {
            return out(null, 500, '查询失败：' . $e->getMessage());
        }
    }

    /**
     * 获取幸福堦值累计次数（幸福权益激活次数）
     * @param int $userId 用户ID
     * @return int
     */
    private function getHappinessEquityCount($userId)
    {
        return HappinessEquityActivation::where('user_id', $userId)
                                        ->where('status', 1)
                                        ->count();
    }

    /**
     * 是否申请专展补贴
     * @param int $userId 用户ID
     * @return int 0-未申请，1-已申请
     */
    private function hasSubsidyApply($userId)
    {
        // 通过mp_exclusive_log表判断是否申请过专展补贴
        $count = ExclusiveLog::where('user_id', $userId)->count();
        return $count > 0 ? 1 : 0;
    }

    /**
     * 是否申请惠民货款
     * @param int $userId 用户ID
     * @return int 0-未申请，1-已申请
     */
    private function hasLoanApply($userId)
    {
        $application = LoanApplication::where('user_id', $userId)->find();
        return $application ? 1 : 0;
    }

    /**
     * 获取申领本金总额（购买商品消费 + 捐款 + 定投）
     * @param int $userId 用户ID
     * @return float
     */
    private function getTotalPrincipal($userId)
    {
        $total = 0;

        // 1. 购买商品消费（Order表，status > 1表示已支付）
        $orderAmount = Order::where('user_id', $userId)
                           ->where('status', '>', 1)
                           ->sum('price');
        $total += $orderAmount ?: 0;

        // 2. 购买商品消费（OrderDailyBonus表，status > 1表示已支付）
        $dailyBonusAmount = OrderDailyBonus::where('user_id', $userId)
                                          ->where('status', '>', 1)
                                          ->sum('price');
        $total += $dailyBonusAmount ?: 0;

        // 3. 购买商品消费（OrderTiyan表，status > 1表示已支付）
        $tiyanAmount = OrderTiyan::where('user_id', $userId)
                                ->where('status', '>', 1)
                                ->sum('price');
        $total += $tiyanAmount ?: 0;

        // 4. 捐款（OrderTongxing表，同心同行订单，status > 1表示已支付）
        $donationAmount = OrderTongxing::where('user_id', $userId)
                                      ->where('status', '>', 1)
                                      ->sum('price');
        $total += $donationAmount ?: 0;

        // 5. 定投（OrderDingtou表，status = 2表示已完成）
        $dingtouAmount = OrderDingtou::where('user_id', $userId)
                                    ->where('status', 2)
                                    ->sum('price');
        $total += $dingtouAmount ?: 0;

        return round($total, 2);
    }

    /**
     * 获取已获得财政资金总额（申领商品返现金额）
     * @param int $userId 用户ID
     * @return float
     */
    private function getTotalFiscalFund($userId)
    {
        // 申领商品返现金额：从UserBalanceLog表中统计返现记录
        // TODO: 需要根据实际业务确定返现的type和log_type值，或者remark标识
        
        // 方案1：通过remark包含"返现"关键字统计
        $total = Db::name('user_balance_log')
                  ->where('user_id', $userId)
                  ->where('change_balance', '>', 0) // 增加余额的记录
                  ->where('remark', 'like', '%返现%') // 包含"返现"的备注
                  ->sum('change_balance');

        // 如果方案1没有数据，可以尝试方案2：根据特定的type和log_type
        // TODO: 需要根据实际业务确定返现的type和log_type值
        // 示例：如果返现记录有特定的type值，可以这样查询：
        // $total = Db::name('user_balance_log')
        //           ->where('user_id', $userId)
        //           ->where('change_balance', '>', 0)
        //           ->where('type', 某个值) // 返现的type值
        //           ->where('log_type', 某个值) // 返现的log_type值
        //           ->sum('change_balance');

        return round($total ?: 0, 2);
    }

    /**
     * 提交民生信息对接中心数据
     * 接收参数：payer_name（受缴人姓名）, payer_id_card（受缴人身份证号）
     */
    public function submitInfo()
    {
        try {
            $req = $this->validate(request(), [
                'payer_name|受缴人姓名' => 'require',
                'payer_id_card|受缴人身份证号' => 'require',
            ]);

            $currentUser = $this->user;

            // 根据姓名和身份证号查找受缴人用户
            $payerUser = User::where('realname', $req['payer_name'])
                           ->where('ic_number', $req['payer_id_card'])
                           ->find();

            if (!$payerUser) {
                return out(null, 10001, '未找到受缴人信息');
            }

            $payerUserId = $payerUser['id'];
            $paymentUserId = $currentUser['id'];

            // 查询已存在的记录（应该在getUserInfo中已创建）
            $existingRecord = PeopleLivelihoodInfo::where('payer_user_id', $payerUserId)->find();
            
            if (!$existingRecord) {
                return out(null, 10001, '未找到缴费记录，请先查询用户信息');
            }

            // 检查是否已缴费（total_payment > 0表示已缴费）
            if ($existingRecord['total_payment'] > 0) {
                return out(null, 10001, '该受缴费人已经缴费，不能重复缴费');
            }

            // 获取配置信息
            $fiscalFundRatio = PeopleLivelihoodConfig::getConfigValue('fiscal_fund_ratio', 0);
            $fixedFee = PeopleLivelihoodConfig::getConfigValue('fixed_fee', 0);
            $requiredFee = PeopleLivelihoodConfig::getConfigValue('required_fee', '手续费/信息对接'); // 从配置表获取固定值

            // 计算钱包总额（民生钱包+惠民钱包+共富钱包+振兴钱包+稳盈钱包+收益钱包）
            $walletTotal = bcadd($payerUser['balance'], $payerUser['digit_balance'], 2); // 民生钱包 + 惠民钱包
            $walletTotal = bcadd($walletTotal, $payerUser['gongfu_wallet'], 2); // + 共富钱包
            $walletTotal = bcadd($walletTotal, $payerUser['zhenxing_wallet'], 2); // + 振兴钱包
            $walletTotal = bcadd($walletTotal, $payerUser['butie'], 2); // + 稳盈钱包
            $walletTotal = bcadd($walletTotal, $payerUser['shouyi_wallet'], 2); // + 收益钱包
            
            // 加上这些钱包提现且未审核的金额（一次查询优化）
            // log_type: 4=民生钱包, 5=惠民钱包, 3=稳盈钱包, 14=振兴钱包, 16=共富钱包, 17=收益钱包, 21=综合钱包
            // type=2表示提现, status=1表示待审核
            $pendingWithdraws = Capital::where('user_id', $payerUserId)
                ->where('type', 2)
                ->where('status', 1)
                ->whereIn('log_type', [4, 5, 3, 14, 16, 17, 21])
                ->field('log_type, sum(abs(amount)) as total_amount')
                ->group('log_type')
                ->select();
            
            // 初始化各钱包待审核提现金额
            $pendingBalance = 0; // 民生钱包 (log_type=4)
            $pendingDigitBalance = 0; // 惠民钱包 (log_type=5)
            $pendingButie = 0; // 稳盈钱包 (log_type=3)
            $pendingZhenxingWallet = 0; // 振兴钱包 (log_type=14)
            $pendingGongfuWallet = 0; // 共富钱包 (log_type=16)
            $pendingShouyiWallet = 0; // 收益钱包 (log_type=17)
            $pendingZongheWallet = 0; // 综合钱包 (log_type=21)
            
            // 将查询结果按 log_type 分配到对应变量
            foreach ($pendingWithdraws as $item) {
                $logType = $item['log_type'];
                $totalAmount = floatval($item['total_amount'] ?? 0);
                switch ($logType) {
                    case 4:
                        $pendingBalance = $totalAmount;
                        break;
                    case 5:
                        $pendingDigitBalance = $totalAmount;
                        break;
                    case 3:
                        $pendingButie = $totalAmount;
                        break;
                    case 14:
                        $pendingZhenxingWallet = $totalAmount;
                        break;
                    case 16:
                        $pendingGongfuWallet = $totalAmount;
                        break;
                    case 17:
                        $pendingShouyiWallet = $totalAmount;
                        break;
                    case 21:
                        $pendingZongheWallet = $totalAmount;
                        break;
                }
            }
            
            // 计算总待审核提现金额
            $pendingWithdrawAmount = bcadd($pendingBalance, $pendingDigitBalance, 2);
            $pendingWithdrawAmount = bcadd($pendingWithdrawAmount, $pendingButie, 2);
            $pendingWithdrawAmount = bcadd($pendingWithdrawAmount, $pendingZhenxingWallet, 2);
            $pendingWithdrawAmount = bcadd($pendingWithdrawAmount, $pendingGongfuWallet, 2);
            $pendingWithdrawAmount = bcadd($pendingWithdrawAmount, $pendingShouyiWallet, 2);
            $pendingWithdrawAmount = bcadd($pendingWithdrawAmount, $pendingZongheWallet, 2); // 综合钱包待审核提现
            $walletTotal = bcadd($walletTotal, $pendingWithdrawAmount, 2);
            

            // 计算总缴费：钱包总额 * (财政资金比例 / 100) + 固定费用
            $ratioAmount = bcmul($walletTotal, bcdiv($fiscalFundRatio, 100, 4), 2);
            $originalTotalPayment = bcadd($ratioAmount, $fixedFee, 2); // 原始缴费金额（用于返现计算）

            // 計算折扣（参考项目购买逻辑）
            
            $discount = 1;
            // VIP用户可能有折扣，这里可以根据需要添加VIP折扣逻辑
            if($currentUser['vip_status'] == 1){
                $discount = 0.9;
            }
            // 使用整数运算避免浮点数精度问题：乘以10
            $discountInt = (int)round($discount * 10);
            $discount = $discountInt / 10;
            
            // 应用折扣计算实际支付金额（使用整数运算避免精度问题）
            // 将原始金额转换为分（乘以100）
            $originalTotalPaymentCents = (int)round((float)$originalTotalPayment * 100);
            // 计算折扣后的金额（分）
            $totalPaymentCents = (int)round($originalTotalPaymentCents * $discountInt / 10);
            $totalPayment = $totalPaymentCents / 100;

            // 检查当前用户余额是否足够（使用topup_balance钱包）
            if ($currentUser['topup_balance'] < $totalPayment) {
                return out(null, 10001, '余额不足，需要' . $totalPayment . '元');
            }

            // 使用已存在的财政编号
            $fiscalNumber = $existingRecord['fiscal_number'];

            // 开启事务
            Db::startTrans();
            try {
                // 重新获取当前用户信息（加锁）
                $currentUser = User::where('id', $currentUser['id'])->lock(true)->find();
                
                // 再次检查余额（使用topup_balance钱包）
                if ($currentUser['topup_balance'] < $totalPayment) {
                    return out(null, 10001, '余额不足，需要' . $totalPayment . '元');
                }

                // 从当前用户的余额钱包（民生钱包）扣除总缴费
                User::changeInc(
                    $currentUser['id'],
                    -$totalPayment,
                    'topup_balance',
                    131, // type: 民生信息对接中心缴费
                    $payerUserId, // relation_id: 受缴人ID
                    1, 
                    '民生信息对接中心缴费',
                    0, // admin_user_id
                    1, // status
                    'MSXX' // sn_prefix
                );

                // 向上返现（参考项目购买逻辑）
                // 获取当前用户的上级关系（1-5级）
                $relation = UserRelation::where('sub_user_id', $payerUser['id'])
                    ->where('level', 'in', [1, 2, 3, 4, 5])
                    ->select();
                
                $map = [
                    1 => 'first_team_reward_ratio',
                    2 => 'second_team_reward_ratio',
                    3 => 'third_team_reward_ratio',
                    4 => 'fourth_team_reward_ratio',
                    5 => 'fifth_team_reward_ratio'
                ];
                
                foreach ($relation as $v) {
                    // 返现金额基于原始缴费金额（折扣前）
                    $reward = round(dbconfig($map[$v['level']]) / 100 * $totalPayment, 2);
                    if ($reward > 0) {
                        // 4级和5级需要检查VIP状态
                        if ($v['level'] == 4 || $v['level'] == 5) {
                            $levelUser = User::where('id', $v['user_id'])->field('id,realname,vip_status')->find();
                            if ($levelUser['vip_status'] != 1) {
                                continue;
                            }
                        }
                        // 给上级增加团队奖励余额
                        User::changeInc(
                            $v['user_id'],
                            $reward,
                            'team_bonus_balance',
                            8,
                            $existingRecord['id'], // 使用缴费记录ID作为relation_id
                            2,
                            '团队奖励' . $v['level'] . '级' . $payerUser['realname'] . '（民生信息对接中心缴费）',
                            0,
                            2,
                            'TD'
                        );
                        // 记录返现日志
                        RelationshipRewardLog::insert([
                            'uid' => $v['user_id'],
                            'reward' => $reward,
                            'son' => $payerUser['id'],
                            'son_lay' => $v['level'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                // 更新记录
                $existingRecord->required_fee = $requiredFee; // 从配置表获取的固定值
                $existingRecord->payment_time = date('Y-m-d H:i:s');
                $existingRecord->payment_user_id = $paymentUserId;
                $existingRecord->fiscal_fund_ratio = $fiscalFundRatio;
                $existingRecord->fixed_fee = $fixedFee;
                $existingRecord->total_payment = $totalPayment;
                // 保存钱包余额（包括待审核提现金额）
                $existingRecord->balance = bcadd($payerUser['balance'], $pendingBalance, 2); // 民生钱包 + 待审核提现
                $existingRecord->digit_balance = bcadd($payerUser['digit_balance'], $pendingDigitBalance, 2); // 惠民钱包 + 待审核提现
                $existingRecord->gongfu_wallet = bcadd($payerUser['gongfu_wallet'], $pendingGongfuWallet, 2); // 共富钱包 + 待审核提现
                $existingRecord->zhenxing_wallet = bcadd($payerUser['zhenxing_wallet'], $pendingZhenxingWallet, 2); // 振兴钱包 + 待审核提现
                $existingRecord->butie = bcadd($payerUser['butie'], $pendingButie, 2); // 稳盈钱包 + 待审核提现
                $existingRecord->shouyi_wallet = bcadd($payerUser['shouyi_wallet'], $pendingShouyiWallet, 2); // 收益钱包 + 待审核提现
                $existingRecord->save();
                
                $info = $existingRecord;

                Db::commit();

                return out([
                    'id' => $info['id'],
                    'fiscal_number' => $fiscalNumber,
                    'total_payment' => number_format($totalPayment, 2, '.', ''), // 实际支付金额（已应用折扣）- 字符串格式避免JSON精度问题
                    'original_total_payment' => number_format((float)$originalTotalPayment, 2, '.', ''), // 原始缴费金额（折扣前）- 字符串格式避免JSON精度问题
                    'discount' => number_format($discount, 1, '.', ''), // 折扣率（字符串格式避免JSON精度问题）
                    'wallet_total' => number_format((float)$walletTotal, 2, '.', ''), // 钱包总额（字符串格式避免JSON精度问题）
                    'fiscal_fund_ratio' => $fiscalFundRatio,
                    'fixed_fee' => $fixedFee,
                ], 0, '提交成功');

            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }

        } catch (Exception $e) {
            return out(null, 500, '提交失败：' . $e->getMessage());
        }
    }

    /**
     * 获取缴费记录
     * 获取当前用户作为受缴人（payer_user_id）的记录，每个用户只有一条
     */
    public function getPaymentRecords()
    {
        try {
            $currentUser = $this->user;

            // 查询当前用户作为受缴人的记录（只有一条）
            $record = PeopleLivelihoodInfo::where('payer_user_id', $currentUser['id'])->find();

            if (!$record) {
                return out(null, 10001, '暂无缴费记录');
            }

            // 如果total_payment = 0，表示未缴费
            if ($record['total_payment'] == 0) {
                return out(null, 10001, '暂无缴费记录');
            }

            // 格式化数据（只有已缴费的记录才返回详情）
            $data = [
                'id' => $record['id'],
                'fiscal_number' => $record['fiscal_number'],
                'required_fee' => $record['required_fee'],
                'payment_time' => $record['payment_time'],
                'payer_name' => $record['payer_name'],
                'payer_id_card' => $record['payer_id_card'],
                'payer_user_id' => $record['payer_user_id'],
                'payment_user_id' => $record['payment_user_id'],
                'fiscal_fund_ratio' => $record['fiscal_fund_ratio'],
                'fixed_fee' => $record['fixed_fee'],
                'total_payment' => $record['total_payment'],
                'created_at' => $record['created_at'],
            ];

            return out($data, 0, '获取成功');

        } catch (Exception $e) {
            return out(null, 500, '获取失败：' . $e->getMessage());
        }
    }

    /**
     * 生成财政编号
     * 格式：姓名首字母大写-年份-邀请码
     * 例如：林秀莲，邀请码8468655 -> LXL-2026-8468655
     * 
     * @param string $realname 姓名
     * @param string $inviteCode 邀请码
     * @return string 财政编号
     */
    private function generateFiscalNumber($realname, $inviteCode)
    {
        // 获取姓名拼音首字母
        $initials = $this->getPinyinInitials($realname);
        // 获取当前年份
        $year = date('Y');
        // 组合财政编号：首字母-年份-邀请码
        return strtoupper($initials) . '-' . $year . '-' . $inviteCode;
    }

    /**
     * 获取中文姓名的拼音首字母
     * 
     * @param string $name 中文姓名
     * @return string 拼音首字母（如：林秀莲 -> LXL）
     */
    private function getPinyinInitials($name)
    {
        if (empty($name)) {
            return '';
        }

        // 拼音首字母映射表（常用汉字）
        $pinyinMap = [
            '林' => 'L', '秀' => 'X', '莲' => 'L',
            '张' => 'Z', '王' => 'W', '李' => 'L', '刘' => 'L', '陈' => 'C',
            '杨' => 'Y', '黄' => 'H', '赵' => 'Z', '吴' => 'W', '周' => 'Z',
            '徐' => 'X', '孙' => 'S', '马' => 'M', '朱' => 'Z', '胡' => 'H',
            '郭' => 'G', '何' => 'H', '高' => 'G', '罗' => 'L', '郑' => 'Z',
            '梁' => 'L', '谢' => 'X', '宋' => 'S', '唐' => 'T', '许' => 'X',
            '韩' => 'H', '冯' => 'F', '邓' => 'D', '曹' => 'C', '彭' => 'P',
            '曾' => 'Z', '肖' => 'X', '田' => 'T', '董' => 'D', '袁' => 'Y',
            '潘' => 'P', '于' => 'Y', '蒋' => 'J', '蔡' => 'C', '余' => 'Y',
            '杜' => 'D', '叶' => 'Y', '程' => 'C', '苏' => 'S', '魏' => 'W',
            '吕' => 'L', '丁' => 'D', '任' => 'R', '沈' => 'S', '姚' => 'Y',
            '卢' => 'L', '姜' => 'J', '崔' => 'C', '钟' => 'Z', '谭' => 'T',
            '陆' => 'L', '汪' => 'W', '范' => 'F', '金' => 'J', '石' => 'S',
            '廖' => 'L', '贾' => 'J', '夏' => 'X', '韦' => 'W', '付' => 'F',
            '方' => 'F', '白' => 'B', '邹' => 'Z', '孟' => 'M', '熊' => 'X',
            '秦' => 'Q', '邱' => 'Q', '江' => 'J', '尹' => 'Y', '薛' => 'X',
            '闫' => 'Y', '段' => 'D', '雷' => 'L', '侯' => 'H', '龙' => 'L',
            '史' => 'S', '陶' => 'T', '黎' => 'L', '贺' => 'H', '顾' => 'G',
            '毛' => 'M', '郝' => 'H', '龚' => 'G', '邵' => 'S', '万' => 'W',
            '钱' => 'Q', '严' => 'Y', '覃' => 'Q', '武' => 'W', '戴' => 'D',
            '莫' => 'M', '孔' => 'K', '向' => 'X', '汤' => 'T', '常' => 'C',
            '温' => 'W', '康' => 'K', '施' => 'S', '文' => 'W', '牛' => 'N',
            '樊' => 'F', '葛' => 'G', '邢' => 'X', '安' => 'A', '齐' => 'Q',
            '易' => 'Y', '乔' => 'Q', '伍' => 'W', '庞' => 'P', '颜' => 'Y',
            '倪' => 'N', '庄' => 'Z', '聂' => 'N', '章' => 'Z', '鲁' => 'L',
            '岳' => 'Y', '翟' => 'Z', '殷' => 'Y', '詹' => 'Z', '申' => 'S',
            '欧' => 'O', '耿' => 'G', '关' => 'G', '兰' => 'L', '焦' => 'J',
            '俞' => 'Y', '左' => 'Z', '柳' => 'L', '甘' => 'G', '祝' => 'Z',
            '包' => 'B', '宁' => 'N', '尚' => 'S', '符' => 'F', '舒' => 'S',
            '阮' => 'R', '柯' => 'K', '纪' => 'J', '梅' => 'M', '童' => 'T',
            '凌' => 'L', '毕' => 'B', '单' => 'D', '季' => 'J', '裴' => 'P',
            '霍' => 'H', '涂' => 'T', '成' => 'C', '苗' => 'M', '谷' => 'G',
            '盛' => 'S', '曲' => 'Q', '翁' => 'W', '冉' => 'R', '骆' => 'L',
            '蓝' => 'L', '路' => 'L', '游' => 'Y', '辛' => 'X', '靳' => 'J',
            '欧阳' => 'OY', '司马' => 'SM', '上官' => 'SG', '诸葛' => 'ZG',
            '东方' => 'DF', '皇甫' => 'HF', '尉迟' => 'YC', '公羊' => 'GY',
            '澹台' => 'TT', '公冶' => 'GY', '宗政' => 'ZZ', '濮阳' => 'PY',
            '淳于' => 'CY', '单于' => 'CY', '太叔' => 'TS', '申屠' => 'ST',
            '公孙' => 'GS', '仲孙' => 'ZS', '轩辕' => 'XY', '令狐' => 'LH',
            '钟离' => 'ZL', '宇文' => 'YW', '长孙' => 'ZS', '慕容' => 'MR',
            '鲜于' => 'XY', '闾丘' => 'LQ', '司徒' => 'ST', '司空' => 'SK',
            '亓官' => 'QG', '司寇' => 'SK', '仉' => 'Z', '督' => 'D',
            '子车' => 'ZC', '颛孙' => 'ZS', '端木' => 'DM', '巫马' => 'WM',
            '公西' => 'GX', '漆雕' => 'QD', '乐正' => 'LZ', '壤驷' => 'RS',
            '公良' => 'GL', '拓跋' => 'TB', '夹谷' => 'JG', '宰父' => 'ZF',
            '谷梁' => 'GL', '晋' => 'J', '楚' => 'C', '闫' => 'Y', '法' => 'F',
            '汝' => 'R', '鄢' => 'Y', '涂' => 'T', '钦' => 'Q', '段干' => 'DG',
            '百里' => 'BL', '东郭' => 'DG', '南门' => 'NM', '呼延' => 'HY',
            '归' => 'G', '海' => 'H', '羊舌' => 'YS', '微生' => 'WS',
            '岳' => 'Y', '帅' => 'S', '缑' => 'G', '亢' => 'K', '况' => 'K',
            '后' => 'H', '有' => 'Y', '琴' => 'Q', '梁丘' => 'LQ', '左丘' => 'ZQ',
            '东门' => 'DM', '西门' => 'XM', '商' => 'S', '牟' => 'M', '佘' => 'S',
            '佴' => 'N', '伯' => 'B', '赏' => 'S', '南宫' => 'NG', '墨' => 'M',
            '哈' => 'H', '谯' => 'Q', '笪' => 'D', '年' => 'N', '爱' => 'A',
            '阳' => 'Y', '佟' => 'T', '第五' => 'DW', '言' => 'Y', '福' => 'F',
        ];

        $initials = '';
        $length = mb_strlen($name, 'UTF-8');
        
        // 先检查复姓（两个字）
        if ($length >= 2) {
            $firstTwo = mb_substr($name, 0, 2, 'UTF-8');
            if (isset($pinyinMap[$firstTwo])) {
                $initials .= $pinyinMap[$firstTwo];
                // 处理剩余字符
                for ($i = 2; $i < $length; $i++) {
                    $char = mb_substr($name, $i, 1, 'UTF-8');
                    if (isset($pinyinMap[$char])) {
                        $initials .= $pinyinMap[$char];
                    } else {
                        // 如果不在映射表中，使用字符的Unicode编码来估算
                        $initials .= $this->getCharInitial($char);
                    }
                }
                return $initials;
            }
        }
        
        // 单字处理
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($name, $i, 1, 'UTF-8');
            if (isset($pinyinMap[$char])) {
                $initials .= $pinyinMap[$char];
            } else {
                // 如果不在映射表中，使用字符的Unicode编码来估算
                $initials .= $this->getCharInitial($char);
            }
        }
        
        return $initials;
    }

    /**
     * 获取单个字符的拼音首字母（备用方法）
     * 基于Unicode编码范围的拼音首字母映射
     * 
     * @param string $char 单个中文字符
     * @return string 拼音首字母
     */
    private function getCharInitial($char)
    {
        $unicode = $this->unicode($char);
        
        // 常用汉字范围 0x4E00-0x9FA5
        // 基于GB2312编码的拼音首字母分布规律进行映射
        if ($unicode >= 0x4E00 && $unicode <= 0x9FA5) {
            $offset = $unicode - 0x4E00;
            
            // 基于GB2312编码的拼音首字母分布区间
            // 这是一个近似映射，覆盖大部分常用汉字
            if ($offset < 200) return 'A';      // A区
            if ($offset < 600) return 'B';      // B区
            if ($offset < 1000) return 'C';      // C区
            if ($offset < 1400) return 'D';      // D区
            if ($offset < 1600) return 'E';      // E区
            if ($offset < 2000) return 'F';     // F区
            if ($offset < 2400) return 'G';     // G区
            if ($offset < 2800) return 'H';     // H区
            if ($offset < 3200) return 'J';     // J区
            if ($offset < 3600) return 'K';     // K区
            if ($offset < 4000) return 'L';     // L区
            if ($offset < 4400) return 'M';     // M区
            if ($offset < 4800) return 'N';     // N区
            if ($offset < 5000) return 'O';     // O区
            if ($offset < 5400) return 'P';     // P区
            if ($offset < 5800) return 'Q';     // Q区
            if ($offset < 6200) return 'R';     // R区
            if ($offset < 6600) return 'S';     // S区
            if ($offset < 7000) return 'T';     // T区
            if ($offset < 7400) return 'W';     // W区
            if ($offset < 7800) return 'X';     // X区
            if ($offset < 8200) return 'Y';     // Y区
            // 剩余部分归为Z区
            return 'Z';
        }
        
        // 扩展A区汉字 0x9FA6-0x9FEF
        if ($unicode >= 0x9FA6 && $unicode <= 0x9FEF) return 'A';
        
        // 扩展B区汉字 0x3400-0x4DB5
        if ($unicode >= 0x3400 && $unicode <= 0x4DB5) {
            $offset = $unicode - 0x3400;
            $mod = $offset % 23;
            $letters = ['A','B','C','D','E','F','G','H','J','K','L','M','N','O','P','Q','R','S','T','W','X','Y','Z'];
            return $letters[$mod % 23];
        }
        
        // 默认返回Z
        return 'Z';
    }

    /**
     * 获取字符的Unicode编码
     * 
     * @param string $char 字符
     * @return int Unicode编码
     */
    private function unicode($char)
    {
        $unicode = unpack('H*', mb_convert_encoding($char, 'UCS-2BE', 'UTF-8'));
        return hexdec($unicode[1]);
    }
}


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

            //计算总计需要缴费
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
            $totalFee = $user['balance'] + $user['digit_balance'] + $user['gongfu_wallet'] + $user['zhenxing_wallet'] + $user['butie'] + $user['shouyi_wallet'];
            $totalFee = bcmul($totalFee, $fiscalFundRatio / 100, 2);
            $totalFee = bcadd($totalFee, $fixedFee, 2);
            $totalFee = format_number($totalFee);

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
                    return out(null, 10001, '已缴费');
                }
            } else {
                // 如果没有数据，生成一条新数据
                $fiscalNumber = date('Ymd') . str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                
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

            // 返回数据
            $data = [
                'user_id' => $user['id'],
                'phone' => $user['phone'],
                'realname' => $user['realname'],
                'ic_number' => $user['ic_number'],
                'register_time' => $user['created_at'], // 注册时间
                'is_shiming' => $user['shiming_status'], // 是否实名：0-未实名，1-已实名
                'minsheng_wallet' => round($user['balance'], 2), // 民生钱包余额
                'gongfu_wallet' => round($user['gongfu_wallet'], 2), // 共富钱包
                'huimin_wallet' => round($user['digit_balance'], 2), // 惠民钱包
                'zhenxing_wallet' => round($user['zhenxing_wallet'], 2), // 振兴钱包
                'wenyin_wallet' => round($user['butie'], 2), // 稳盈钱包
                'shouyi_wallet' => round($user['shouyi_wallet'], 2), // 收益钱包
                'other_wallet' => round($user['digit_balance'] + $user['zhenxing_wallet'] + $user['butie'] + $user['shouyi_wallet'], 2), // 其他钱包
                'gold_balance' => round($goldBalance, 2), // 持有黄金（克）
                'happiness_equity_count' => $this->getHappinessEquityCount($user['id']), // 幸福堦值累计次数
                'has_subsidy_apply' => $this->hasSubsidyApply($user['id']), // 是否申请专展补贴
                'has_loan_apply' => $this->hasLoanApply($user['id']), // 是否申请惠民货款
                'total_principal' => $this->getTotalPrincipal($user['id']), // 申领本金总额
                'total_fiscal_fund' => $this->getTotalFiscalFund($user['id']), // 已获得财政资金总额
                'configs' => $configs, // 配置信息列表
                'payment_status' => $paymentStatus, // 缴费状态：未缴费/已缴费
                'fiscal_number' => $fiscalNumber, // 财政编号（未缴费时返回）
                'total_fee' => $totalFee, // 总计需要缴费
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

            // 计算总缴费：钱包总额 * (财政资金比例 / 100) + 固定费用
            $ratioAmount = bcmul($walletTotal, bcdiv($fiscalFundRatio, 100, 4), 2);
            $totalPayment = bcadd($ratioAmount, $fixedFee, 2);

            // 检查当前用户余额是否足够
            if ($currentUser['balance'] < $totalPayment) {
                return out(null, 10001, '余额不足，需要' . $totalPayment . '元');
            }

            // 使用已存在的财政编号
            $fiscalNumber = $existingRecord['fiscal_number'];

            // 开启事务
            Db::startTrans();
            try {
                // 重新获取当前用户信息（加锁）
                $currentUser = User::where('id', $currentUser['id'])->lock(true)->find();
                
                // 再次检查余额
                if ($currentUser['balance'] < $totalPayment) {
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

                // 更新记录
                $existingRecord->required_fee = $requiredFee; // 从配置表获取的固定值
                $existingRecord->payment_time = date('Y-m-d H:i:s');
                $existingRecord->payment_user_id = $paymentUserId;
                $existingRecord->fiscal_fund_ratio = $fiscalFundRatio;
                $existingRecord->fixed_fee = $fixedFee;
                $existingRecord->total_payment = $totalPayment;
                $existingRecord->save();
                
                $info = $existingRecord;

                Db::commit();

                return out([
                    'id' => $info['id'],
                    'fiscal_number' => $fiscalNumber,
                    'total_payment' => $totalPayment,
                    'wallet_total' => $walletTotal,
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
}


<?php
declare(strict_types=1);

namespace app\api\controller;

use app\model\LoanProduct;
use app\model\LoanProductGradient;
use app\model\LoanApplication;
use app\model\LoanConfig;
use app\model\User;
use app\model\Order;
use app\model\OrderDailyBonus;
use app\model\Project;
use think\facade\Db;
use think\facade\Validate;

class LoanController extends AuthController
{
    /**
     * 获取贷款产品信息
     * @return \think\response\Json
     */
    public function getProduct()
    {
        try {
            // $req = $this->validate(request(), [
            //     'product_id' => 'require|number'
            // ]);
            
            $productId = 1;

            // 获取产品信息
            $product = LoanProduct::with(['gradients' => function($query) {
                $query->where('status', 1)->order('loan_days asc');
            }])->where('id', $productId)
               ->where('status', 1)
               ->find();

            if (!$product) {
                return out(null, 404, '产品不存在或已禁用');
            }

            // 格式化产品数据
            $productData = [
                'id' => $product->id,
                'name' => $product->name,
                'min_amount' => $product->min_amount,
                'max_amount' => $product->max_amount,
                'interest_type' => $product->interest_type,
                'interest_type_text' => $product->getInterestTypeTextAttr(null, $product->toArray()),
                'overdue_interest_rate' => $product->overdue_interest_rate,
                'max_overdue_days' => $product->max_overdue_days,
                'gradients' => []
            ];

            // 格式化梯度数据
            foreach ($product->gradients as $gradient) {
                $productData['gradients'][] = [
                    'id' => $gradient->id,
                    'loan_days' => $gradient->loan_days,
                    'installment_count' => $gradient->installment_count,
                    'interest_rate' => $gradient->interest_rate,
                    'status' => $gradient->status
                ];
            }

            return out($productData, 200, '获取成功');

        } catch (\Exception $e) {
            return out(null, 500, '获取产品信息失败：' . $e->getMessage());
        }
    }

    /**
     * 提交借款申请
     * @return \think\response\Json
     */
    public function submitApplication()
    {
        try {
            $req = $this->validate(request(), [
                'product_id' => 'require|number',
                'gradient_id' => 'require|number',
                'loan_amount' => 'require|float|gt:0',
                'realname' => 'require|max:50',
                'id_card' => 'require|length:18',
                'phone' => 'require|mobile',
                'pay_password|支付密码' => 'require'
            ]);

            // 验证支付密码
            $user = $this->user;
            if (empty($user['pay_password'])) {
                return out(null, 400, '请先设置支付密码');
            }
            
            if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
                return out(null, 400, '支付密码错误');
            }

            // 验证产品是否存在且启用
            $product = LoanProduct::where('id', $req['product_id'])
                                 ->where('status', 1)
                                 ->find();
            if (!$product) {
                return out(null, 404, '产品不存在或已禁用');
            }

            // 验证梯度是否存在且启用
            $gradient = LoanProductGradient::where('id', $req['gradient_id'])
                                          ->where('product_id', $req['product_id'])
                                          ->where('status', 1)
                                          ->find();
            if (!$gradient) {
                return out(null, 404, '贷款梯度不存在或已禁用');
            }

            // 检查用户资格和计算最大贷款限额
            $maxLoanAmount = $this->checkUserQualificationAndGetMaxAmount($user['id']);
            if ($maxLoanAmount === false) {
                return out(null, 400, '您尚未完成任一五福临门板块申领，无法申请贷款');
            }
            
            // 检查幸福助力券数量
            $requiredTickets = LoanConfig::getConfig('xingfu_tickets_num', 10);
            if ($user['xingfu_tickets'] < $requiredTickets) {
                return out(null, 400, "申请贷款需要{$requiredTickets}张幸福助力券，您当前有{$user['xingfu_tickets']}张");
            }
            
            // 验证贷款金额是否在范围内
            $actualMaxAmount = min($product->max_amount, $maxLoanAmount);
            if ($req['loan_amount'] < $product->min_amount || $req['loan_amount'] > $actualMaxAmount) {
                return out(null, 400, "贷款金额必须在{$product->min_amount}元到{$actualMaxAmount}元之间");
            }

            // 验证用户申请次数限制
            $maxApplyPerDay = LoanConfig::getConfig('loan_max_apply_per_day', 3);
            $todayApplyCount = LoanApplication::where('user_id', $this->user['id'])
                                             ->whereTime('created_at', 'today')
                                             ->count();
            if ($todayApplyCount >= $maxApplyPerDay) {
                return out(null, 400, "今日申请次数已达上限({$maxApplyPerDay}次)");
            }

            // 验证用户是否有未完成的申请
            $pendingApplication = LoanApplication::where('user_id', $this->user['id'])
                                                ->whereIn('status', [1, 2, 4]) // 待审核、已通过、已放款
                                                ->find();
            if ($pendingApplication) {
                return out(null, 400, '您有未完成的贷款申请，请等待处理完成后再申请');
            }

            // 计算利息和总金额
            $loanDays = $gradient->loan_days;
            $interestRate = bcdiv($gradient->interest_rate, '100', 4); // 利息率是百分比，需要除以100
            $totalInterest = bcmul($req['loan_amount'], $interestRate, 4) * $loanDays;
            $totalAmount = bcadd($req['loan_amount'], (string)$totalInterest, 2);
            
            // 计算月供金额（总金额除以分期数）
            $monthlyPayment = bcdiv($totalAmount, (string)$gradient->installment_count, 2);

            Db::startTrans();
            try {
                // 创建贷款申请
                $application = LoanApplication::create([
                    'user_id' => $this->user['id'],
                    'product_id' => $req['product_id'],
                    'gradient_id' => $req['gradient_id'],
                    'loan_amount' => $req['loan_amount'],
                    'loan_days' => $loanDays,
                    'installment_count' => $gradient->installment_count,
                    'interest_rate' => $interestRate,
                    'total_interest' => $totalInterest,
                    'total_amount' => $totalAmount,
                    'monthly_payment' => $monthlyPayment,
                    'status' => 1, // 待审核
                    'realname' => $req['realname'],
                    'id_card' => $req['id_card'],
                    'phone' => $req['phone']
                ]);

                // 检查是否需要自动审核
                $auditAuto = LoanConfig::getConfig('loan_audit_auto', 0);
                $autoApproveAmount = LoanConfig::getConfig('loan_auto_approve_amount', 5000);
                
                if ($auditAuto && $req['loan_amount'] <= $autoApproveAmount) {
                    // 自动审核通过
                    $application->status = 2; // 已通过
                    $application->audit_user_id = 0; // 系统自动审核
                    $application->audit_time = date('Y-m-d H:i:s');
                    $application->audit_remark = '系统自动审核通过';
                    $application->save();
                }

                Db::commit();

                // 返回申请信息
                $result = [
                    'application_id' => $application->id,
                    'loan_amount' => $application->loan_amount,
                    'loan_days' => $application->loan_days,
                    'installment_count' => $application->installment_count,
                    'interest_rate' => $application->interest_rate,
                    'total_interest' => $application->total_interest,
                    'total_amount' => $application->total_amount,
                    'status' => $application->status,
                    'status_text' => $application->getStatusTextAttr(null, $application->toArray()),
                    'created_at' => $application->created_at
                ];

                return out($result, 200, '申请提交成功');

            } catch (\Exception $e) {
                Db::rollback();
                return out(null, 500, '申请提交失败：' . $e->getMessage());
            }

        } catch (\Exception $e) {
            return out(null, 500, '申请提交失败：' . $e->getMessage());
        }
    }

    /**
     * 获取我的贷款申请列表
     * @return \think\response\Json
     */
    public function getMyApplications()
    {
        try {
            $req = $this->validate(request(), [
                'page' => 'number|default:1',
                'limit' => 'number|default:10'
            ]);
            
            $user = $this->user;
            
            $applications = LoanApplication::with(['product', 'gradient'])
                                          ->where('user_id', $user['id'])
                                          ->order('id desc')
                                          ->paginate([
                                              'list_rows' => $req['limit'],
                                              'page' => $req['page']
                                          ]);

            $data = [];
            foreach ($applications as $application) {
                $data[] = [
                    'id' => $application->id,
                    'product_name' => $application->product->name ?? '',
                    'loan_amount' => $application->loan_amount,
                    'loan_days' => $application->loan_days,
                    'installment_count' => $application->installment_count,
                    'interest_rate' => $application->interest_rate,
                    'total_interest' => $application->total_interest,
                    'total_amount' => $application->total_amount,
                    'status' => $application->status,
                    'status_text' => $application->getStatusTextAttr(null, $application->toArray()),
                    'audit_remark' => $application->audit_remark,
                    'created_at' => $application->created_at,
                    'audit_time' => $application->audit_time,
                    'disburse_time' => $application->disburse_time
                ];
            }

            return out([
                'list' => $data,
                'total' => $applications->total(),
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage()
            ], 200, '获取成功');

        } catch (\Exception $e) {
            return out(null, 500, '获取申请列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取用户贷款资格
     * @return \think\response\Json
     */
    public function getUserQualification()
    {
        try {
            $user = $this->user;
            
            // 检查用户资格和获取最大贷款限额
            $maxLoanAmount = $this->checkUserQualificationAndGetMaxAmount($user['id']);
            
            // 检查幸福助力券数量
            $requiredTickets = LoanConfig::getConfig('xingfu_tickets_num', 10);
            $hasEnoughTickets = $user['xingfu_tickets'] >= $requiredTickets;
            
            if ($maxLoanAmount === false) {
                return out([
                    'qualified' => false,
                    'max_loan_amount' => 0,
                    'message' => '您尚未完成任一五福临门板块申领，无法申请贷款',
                    'xingfu_tickets' => $user['xingfu_tickets'],
                    'required_tickets' => $requiredTickets,
                    'has_enough_tickets' => $hasEnoughTickets
                ], 200, '获取成功');
            }
            
            if (!$hasEnoughTickets) {
                return out([
                    'qualified' => false,
                    'max_loan_amount' => $maxLoanAmount,
                    'message' => "申请贷款需要{$requiredTickets}张幸福助力券，您当前有{$user['xingfu_tickets']}张",
                    'xingfu_tickets' => $user['xingfu_tickets'],
                    'required_tickets' => $requiredTickets,
                    'has_enough_tickets' => $hasEnoughTickets
                ], 200, '获取成功');
            }
            
            // 获取所有可用的贷款产品
            $products = LoanProduct::where('status', 1)->select();
            $availableProducts = [];
            
            foreach ($products as $product) {
                $actualMaxAmount = min($product->max_amount, $maxLoanAmount);
                if ($actualMaxAmount >= $product->min_amount) {
                    $availableProducts[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'min_amount' => $product->min_amount,
                        'max_amount' => $actualMaxAmount,
                        'original_max_amount' => $product->max_amount
                    ];
                }
            }
            
            return out([
                'qualified' => true,
                'max_loan_amount' => $maxLoanAmount,
                'available_products' => $availableProducts,
                'message' => '您已具备贷款资格',
                'xingfu_tickets' => $user['xingfu_tickets'],
                'required_tickets' => $requiredTickets,
                'has_enough_tickets' => $hasEnoughTickets
            ], 200, '获取成功');
            
        } catch (\Exception $e) {
            return out(null, 500, '获取用户资格失败：' . $e->getMessage());
        }
    }

    /**
     * 获取贷款申请详情
     * @return \think\response\Json
     */
    public function getApplicationDetail()
    {
        try {
            $req = $this->validate(request(), [
                'application_id' => 'require|number'
            ]);
            
            $user = $this->user;

            $application = LoanApplication::with(['product', 'gradient', 'repaymentPlans'])
                                         ->where('id', $req['application_id'])
                                         ->where('user_id', $user['id'])
                                         ->find();

            if (!$application) {
                return out(null, 404, '申请记录不存在');
            }

            $data = [
                'id' => $application->id,
                'product_name' => $application->product->name ?? '',
                'loan_amount' => $application->loan_amount,
                'loan_days' => $application->loan_days,
                'installment_count' => $application->installment_count,
                'interest_rate' => $application->interest_rate,
                'total_interest' => $application->total_interest,
                'total_amount' => $application->total_amount,
                'status' => $application->status,
                'status_text' => $application->getStatusTextAttr(null, $application->toArray()),
                'audit_remark' => $application->audit_remark,
                'created_at' => $application->created_at,
                'audit_time' => $application->audit_time,
                'disburse_time' => $application->disburse_time,
                'repayment_plans' => []
            ];

            // 添加还款计划信息
            foreach ($application->repaymentPlans as $plan) {
                $data['repayment_plans'][] = [
                    'id' => $plan->id,
                    'period' => $plan->period,
                    'due_date' => $plan->due_date,
                    'principal' => $plan->principal,
                    'interest' => $plan->interest,
                    'total_amount' => $plan->total_amount,
                    'paid_amount' => $plan->paid_amount,
                    'remaining_amount' => $plan->remaining_amount,
                    'status' => $plan->status,
                    'status_text' => $plan->getStatusTextAttr(null, $plan->toArray()),
                    'overdue_days' => $plan->overdue_days,
                    'overdue_interest' => $plan->overdue_interest
                ];
            }

            return out($data, 200, '获取成功');

        } catch (\Exception $e) {
            return out(null, 500, '获取申请详情失败：' . $e->getMessage());
        }
    }

    /**
     * 检查用户资格并获取最大贷款限额
     * @param int $userId 用户ID
     * @return int|false 最大贷款限额，如果不合格返回false
     */
    private function checkUserQualificationAndGetMaxAmount($userId)
    {
        // 获取用户订单和日返订单
        $orders = Order::where('user_id', $userId)
                      ->where('status', 'in', [2, 4]) // 已支付或已完成状态
                      ->select();
        
        $dailyBonusOrders = OrderDailyBonus::where('user_id', $userId)
                                          ->where('status', 'in', [2, 4]) // 已支付或已完成状态
                                          ->select();

        // 如果没有订单，返回false
        if ($orders->isEmpty() && $dailyBonusOrders->isEmpty()) {
            return false;
        }

        // 获取各项目组的项目ID
        $projectGroups = [];
        for ($i = 7; $i <= 11; $i++) {
            // 获取普通项目（daily_bonus_ratio = 0）
            $projectGroups[$i]['normal'] = Project::where('project_group_id', $i)
                                                 ->where('status', 1)
                                                 ->where('daily_bonus_ratio', '=', 0)
                                                 ->column('id');
            
            // 获取日返项目（daily_bonus_ratio > 0）
            $projectGroups[$i]['daily'] = Project::where('project_group_id', $i)
                                                ->where('status', 1)
                                                ->where('daily_bonus_ratio', '>', 0)
                                                ->column('id');
        }

        // 获取用户订单的项目ID
        $orderProjectIds = $orders->column('project_id');
        $dailyOrderProjectIds = $dailyBonusOrders->column('project_id');

        // 检查用户是否完成了任一项目组
        $maxLoanAmount = 0;
        $completedGroups = [];

        foreach ($projectGroups as $groupId => $projects) {
            // 检查普通项目是否全部完成
            $normalCompleted = !empty($projects['normal']) && 
                              count(array_intersect($projects['normal'], $orderProjectIds)) == count($projects['normal']);
            
            // 检查日返项目是否全部完成
            $dailyCompleted = !empty($projects['daily']) && 
                             count(array_intersect($projects['daily'], $dailyOrderProjectIds)) == count($projects['daily']);
            
            // 如果该组项目全部完成
            if ($normalCompleted && $dailyCompleted) {
                $completedGroups[] = $groupId;
                
                // 获取对应的贷款限额配置
                $configKey = 'loan_finish_pro' . ($groupId - 6); // 7->1, 8->2, 9->3, 10->4, 11->5
                $loanLimit = LoanConfig::getConfig($configKey, 0);
                
                // 取最大值
                $maxLoanAmount = max($maxLoanAmount, $loanLimit);
            }
        }

        // 如果没有完成任何项目组，返回false
        if (empty($completedGroups)) {
            return false;
        }

        return $maxLoanAmount;
    }

    /**
     * 逾期还款
     */
    public function overdueRepayment()
    {
        try {
            $req = $this->validate(request(), [
                'plan_id' => 'require|number',
                'repayment_amount' => 'require|float|gt:0',
                'wallet_type' => 'require|number', // 钱包类型
                'pay_password' => 'require|length:6'
            ]);

            // 验证支付密码
            if (!$this->user || $this->user['pay_password'] !== md5($req['pay_password'])) {
                return out(null, 10001, '支付密码错误');
            }

            // 钱包类型映射
            $walletTypeMap = [
                1 => ['field' => 'topup_balance', 'name' => '充值余额'],
                2 => ['field' => 'team_bonus_balance', 'name' => '荣誉钱包'],
                3 => ['field' => 'butie', 'name' => '稳盈钱包'],
                4 => ['field' => 'balance', 'name' => '民生钱包'],
                5 => ['field' => 'digit_balance', 'name' => '收益钱包'],
                6 => ['field' => 'integral', 'name' => '积分'],
                7 => ['field' => 'appreciating_wallet', 'name' => '幸福收益'],
                8 => ['field' => 'butie_lock', 'name' => '稳赢钱包转入'],
                9 => ['field' => 'lottery_tickets', 'name' => '抽奖卷'],
                10 => ['field' => 'tiyan_wallet_lock', 'name' => '体验钱包预支金'],
                11 => ['field' => 'tiyan_wallet', 'name' => '体验钱包'],
                12 => ['field' => 'xingfu_tickets', 'name' => '幸福助力卷'],
            ];

            // 验证钱包类型
            if (!isset($walletTypeMap[$req['wallet_type']])) {
                return out(null, 10001, '不支持的还款钱包类型');
            }

            // 获取支持的钱包类型配置
            $supportedTypes = \app\model\LoanConfig::getConfig('back_money_types', '1,2,3,4,5');
            $supportedTypes = explode(',', $supportedTypes);
            if (!in_array($req['wallet_type'], $supportedTypes)) {
                return out(null, 10001, '该钱包类型不支持还款');
            }

            $plan = LoanRepaymentPlan::find($req['plan_id']);
            if (!$plan) {
                return out(null, 10001, '还款计划不存在');
            }

            // 验证用户权限
            if ($plan->user_id != $this->user['id']) {
                return out(null, 10001, '无权操作此还款计划');
            }

            if ($plan->status != 3) {
                return out(null, 10001, '该期未逾期');
            }

            $totalAmount = bcadd($plan->remaining_amount, $plan->overdue_interest, 2);
            if ($req['repayment_amount'] > $totalAmount) {
                return out(null, 10001, '还款金额不能大于应还总额');
            }

            // 获取钱包字段名和名称
            $walletField = $walletTypeMap[$req['wallet_type']]['field'];
            $walletName = $walletTypeMap[$req['wallet_type']]['name'];

            // 检查用户钱包余额是否足够
            if ($this->user[$walletField] < $req['repayment_amount']) {
                return out(null, 10001, "{$walletName}余额不足，请先充值");
            }

            Db::startTrans();
            try {
                // 扣除用户钱包余额
                \app\model\User::changeInc(
                    $this->user['id'],
                    -$req['repayment_amount'],
                    $walletField,
                    108, // 交易类型：逾期还款
                    $plan->id,
                    2, // 支出
                    "逾期还款({$walletName})",
                    0,
                    1
                );

                // 创建还款记录
                \app\model\LoanRepaymentRecord::create([
                    'plan_id' => $plan->id,
                    'application_id' => $plan->application_id,
                    'user_id' => $this->user['id'],
                    'repayment_amount' => $req['repayment_amount'],
                    'repayment_type' => 3, // 逾期还款
                    'repayment_method' => 2, // 手动还款
                    'wallet_type' => $req['wallet_type'], // 记录使用的钱包类型
                    'remark' => $req['remark'] ?? "使用{$walletName}还款"
                ]);

                // 更新还款计划
                $plan->paid_amount += $req['repayment_amount'];
                $plan->remaining_amount = $totalAmount - $plan->paid_amount;
                
                if ($plan->remaining_amount <= 0) {
                    $plan->status = 2; // 已还款
                    $plan->overdue_days = 0;
                    $plan->overdue_interest = 0;
                }
                
                $plan->save();

                // 发送还款成功通知
                $this->sendRepaymentSuccessNotification($plan, $walletName);

                Db::commit();
                return out(null, 0, "使用{$walletName}逾期还款成功");
            } catch (\Exception $e) {
                Db::rollback();
                return out(null, 10001, '逾期还款失败：' . $e->getMessage());
            }

        } catch (\Exception $e) {
            return out(null, 500, '逾期还款失败：' . $e->getMessage());
        }
    }

    /**
     * 获取逾期还款计划列表
     */
    public function getOverduePlans()
    {
        try {
            $req = $this->validate(request(), [
                'page' => 'integer|min:1',
                'limit' => 'integer|min:1|max:50'
            ]);

            $page = $req['page'] ?? 1;
            $limit = $req['limit'] ?? 10;

            $builder = LoanRepaymentPlan::with(['application'])
                ->where('user_id', $this->user['id'])
                ->where('status', 3); // 逾期状态

            $total = $builder->count();
            $plans = $builder->page($page, $limit)
                ->order('overdue_days desc, due_date asc')
                ->select();

            $data = [];
            foreach ($plans as $plan) {
                $data[] = [
                    'id' => $plan->id,
                    'application_id' => $plan->application_id,
                    'period' => $plan->period,
                    'due_date' => $plan->due_date,
                    'remaining_amount' => $plan->remaining_amount,
                    'overdue_days' => $plan->overdue_days,
                    'overdue_interest' => $plan->overdue_interest,
                    'total_amount' => bcadd($plan->remaining_amount, $plan->overdue_interest, 2),
                    'loan_amount' => $plan->application->loan_amount
                ];
            }

            return out([
                'list' => $data,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ], 0, '获取成功');

        } catch (\Exception $e) {
            return out(null, 500, '获取逾期计划失败：' . $e->getMessage());
        }
    }

    /**
     * 获取支持的钱包类型列表
     */
    public function getSupportedWalletTypes()
    {
        try {
            // 钱包类型映射
            $walletTypeMap = [
                1 => ['field' => 'topup_balance', 'name' => '充值余额'],
                2 => ['field' => 'team_bonus_balance', 'name' => '荣誉钱包'],
                3 => ['field' => 'butie', 'name' => '稳盈钱包'],
                4 => ['field' => 'balance', 'name' => '民生钱包'],
                5 => ['field' => 'digit_balance', 'name' => '收益钱包'],
                6 => ['field' => 'integral', 'name' => '积分'],
                7 => ['field' => 'appreciating_wallet', 'name' => '幸福收益'],
                8 => ['field' => 'butie_lock', 'name' => '稳赢钱包转入'],
                9 => ['field' => 'lottery_tickets', 'name' => '抽奖卷'],
                10 => ['field' => 'tiyan_wallet_lock', 'name' => '体验钱包预支金'],
                11 => ['field' => 'tiyan_wallet', 'name' => '体验钱包'],
                12 => ['field' => 'xingfu_tickets', 'name' => '幸福助力卷'],
            ];

            // 获取支持的钱包类型配置
            $supportedTypes = \app\model\LoanConfig::getConfig('back_money_types', '1,2,3,4,5');
            $supportedTypes = explode(',', $supportedTypes);

            $data = [];
            foreach ($supportedTypes as $type) {
                $type = (int)trim($type);
                if (isset($walletTypeMap[$type])) {
                    $data[] = [
                        'type' => $type,
                        'name' => $walletTypeMap[$type]['name'],
                        // 'field' => $walletTypeMap[$type]['field'],
                        'balance' => $this->user[$walletTypeMap[$type]['field']] ?? 0
                    ];
                }
            }

            return out($data, 0, '获取成功');

        } catch (\Exception $e) {
            return out(null, 500, '获取钱包类型失败：' . $e->getMessage());
        }
    }

    /**
     * 发送还款成功通知
     */
    private function sendRepaymentSuccessNotification($plan)
    {
        try {
            $user = User::find($plan->user_id);
            $application = LoanApplication::find($plan->application_id);
            
            if (!$user || !$application) {
                return false;
            }

            $title = '逾期还款成功通知';
            $content = "尊敬的{$user['realname']}，您的逾期还款已成功！\n\n";
            $content .= "还款详情：\n";
            $content .= "• 贷款金额：{$application->loan_amount}元\n";
            $content .= "• 还款期数：第{$plan->period}期\n";
            $content .= "• 还款金额：" . bcadd($plan->remaining_amount, $plan->overdue_interest, 2) . "元\n";
            $content .= "• 还款时间：" . date('Y-m-d H:i:s') . "\n\n";
            $content .= "感谢您的及时还款，请继续保持良好的信用记录。";

            // 创建消息
            $message = \app\model\NoticeMessage::create([
                'title' => $title,
                'content' => $content,
                'type' => 1 // 系统通知
            ]);

            // 为用户创建消息记录
            \app\model\NoticeMessageUser::create([
                'user_id' => $plan->user_id,
                'message_id' => $message->id,
                'is_read' => 0,
                'read_time' => null
            ]);

            \think\facade\Log::info("还款成功通知发送成功", [
                'plan_id' => $plan->id,
                'user_id' => $plan->user_id,
                'message_id' => $message->id
            ]);

            return true;
        } catch (\Exception $e) {
            \think\facade\Log::error('发送还款成功通知失败：' . $e->getMessage());
            return false;
        }
    }
}

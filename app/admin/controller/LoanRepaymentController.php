<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\LoanRepaymentPlan;
use app\model\LoanRepaymentRecord;
use app\model\LoanApplication;
use app\model\User;
use think\facade\Db;
use think\facade\View;

class LoanRepaymentController extends AuthController
{
    /**
     * 还款计划列表
     */
    public function planList()
    {
        $req = request()->param();
        
        $builder = LoanRepaymentPlan::with(['user', 'application']);
        
        // 搜索条件
        if (!empty($req['user_id'])) {
            $builder->where('user_id', $req['user_id']);
        }
        if (!empty($req['phone'])) {
            $builder->whereHas('user', function($query) use ($req) {
                $query->where('phone', 'like', '%' . $req['phone'] . '%');
            });
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }
        if (!empty($req['due_date_start'])) {
            $builder->where('due_date', '>=', $req['due_date_start']);
        }
        if (!empty($req['due_date_end'])) {
            $builder->where('due_date', '<=', $req['due_date_end']);
        }
        
        $builder->order('due_date asc, id desc');
        
        $data = $builder->paginate(['query' => $req])->each(function ($item, $key) {
            $item->status_text = $item->getStatusTextAttr(null, $item->toArray());
            return $item;
        });

        View::assign('req', $req);
        View::assign('data', $data);
        View::assign('statusMap', LoanRepaymentPlan::$statusMap);

        return View::fetch('loan_repayment/plan_list');
    }

    /**
     * 还款记录列表
     */
    public function recordList()
    {
        $req = request()->param();
        
        $builder = LoanRepaymentRecord::with(['user', 'application', 'plan']);
        
        // 搜索条件
        if (!empty($req['user_id'])) {
            $builder->where('user_id', $req['user_id']);
        }
        if (!empty($req['phone'])) {
            $builder->whereHas('user', function($query) use ($req) {
                $query->where('phone', 'like', '%' . $req['phone'] . '%');
            });
        }
        if (isset($req['repayment_type']) && $req['repayment_type'] !== '') {
            $builder->where('repayment_type', $req['repayment_type']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('created_at', '>=', $req['start_date']);
        }
        if (!empty($req['end_date'])) {
            $builder->where('created_at', '<=', $req['end_date']);
        }
        
        $builder->order('id desc');
        
        $data = $builder->paginate(['query' => $req])->each(function ($item, $key) {
            $item->repayment_type_text = $item->getRepaymentTypeTextAttr(null, $item->toArray());
            $item->repayment_method_text = $item->getRepaymentMethodTextAttr(null, $item->toArray());
            return $item;
        });

        View::assign('req', $req);
        View::assign('data', $data);
        View::assign('repaymentTypeMap', LoanRepaymentRecord::$repaymentTypeMap);
        View::assign('repaymentMethodMap', LoanRepaymentRecord::$repaymentMethodMap);

        return View::fetch('loan_repayment/record_list');
    }

    /**
     * 逾期列表
     */
    public function overdueList()
    {
        $req = request()->param();
        
        $builder = LoanRepaymentPlan::with(['user', 'application'])
            ->where('status', 3); // 逾期状态
        
        // 搜索条件
        if (!empty($req['user_id'])) {
            $builder->where('user_id', $req['user_id']);
        }
        if (!empty($req['phone'])) {
            $builder->whereHas('user', function($query) use ($req) {
                $query->where('phone', 'like', '%' . $req['phone'] . '%');
            });
        }
        if (!empty($req['overdue_days_min'])) {
            $builder->where('overdue_days', '>=', $req['overdue_days_min']);
        }
        if (!empty($req['overdue_days_max'])) {
            $builder->where('overdue_days', '<=', $req['overdue_days_max']);
        }
        
        $builder->order('overdue_days desc, due_date asc');
        
        $data = $builder->paginate([
            'list_rows' => 5, // 每页显示20条
            'query' => $req
        ])->each(function ($item, $key) {
            $item->status_text = $item->getStatusTextAttr(null, $item->toArray());
            return $item;
        });

        View::assign('req', $req);
        View::assign('data', $data);

        return View::fetch('loan_repayment/overdue_list');
    }

    /**
     * 获取用户钱包余额
     */
    public function getUserWalletBalances()
    {
        $planId = request()->param('plan_id');
        
        $plan = LoanRepaymentPlan::with(['user'])->find($planId);
        if (!$plan) {
            return out(null, 10001, '还款计划不存在');
        }

        $user = User::find($plan->user_id);
        if (!$user) {
            return out(null, 10001, '用户不存在');
        }

        // 钱包类型映射
        $walletTypeMap = [
            1 => ['field' => 'topup_balance', 'name' => '充值余额'],
            2 => ['field' => 'team_bonus_balance', 'name' => '荣誉钱包'],
            3 => ['field' => 'butie', 'name' => '稳盈钱包'],
            4 => ['field' => 'balance', 'name' => '民生钱包'],
            5 => ['field' => 'digit_balance', 'name' => '惠民钱包'],
        ];

        // 获取支持的钱包类型配置
        $supportedTypes = \app\model\LoanConfig::getConfig('back_money_types', '1,2,3,4,5');
        $supportedTypes = explode(',', $supportedTypes);

        $data = [];
        foreach ($supportedTypes as $type) {
            $type = (int)trim($type);
            if (isset($walletTypeMap[$type])) {
                $data[$type] = [
                    'name' => $walletTypeMap[$type]['name'],
                    'balance' => $user[$walletTypeMap[$type]['field']] ?? 0
                ];
            }
        }

        return out($data, 0, '获取成功');
    }

    /**
     * 手动还款
     */
    public function manualRepay()
    {
        $req = request()->param();
        
        $this->validate($req, [
            'plan_id' => 'require|number',
            'repayment_amount' => 'require|float|gt:0',
            'wallet_type' => 'require|number',
            'remark' => 'max:500'
        ]);

        $plan = LoanRepaymentPlan::find($req['plan_id']);
        if (!$plan) {
            return out(null, 10001, '还款计划不存在');
        }

        if ($plan->status == 2) {
            return out(null, 10001, '该期已还款');
        }

        // 钱包类型映射
        $walletTypeMap = [
            1 => ['field' => 'topup_balance', 'name' => '充值余额'],
            2 => ['field' => 'team_bonus_balance', 'name' => '荣誉钱包'],
            3 => ['field' => 'butie', 'name' => '稳盈钱包'],
            4 => ['field' => 'balance', 'name' => '民生钱包'],
            5 => ['field' => 'digit_balance', 'name' => '惠民钱包'],
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

        $totalAmount = bcadd($plan->remaining_amount, $plan->overdue_interest, 2);
        if ($req['repayment_amount'] > $totalAmount) {
            return out(null, 10001, '还款金额不能大于应还总额');
        }

        // 获取钱包字段名和名称
        $walletField = $walletTypeMap[$req['wallet_type']]['field'];
        $walletName = $walletTypeMap[$req['wallet_type']]['name'];

        // 检查用户钱包余额
        $user = User::find($plan->user_id);
        if ($user[$walletField] < $req['repayment_amount']) {
            return out(null, 10001, "{$walletName}余额不足，无法还款");
        }

        Db::startTrans();
        try {
            // 扣除用户钱包余额
            \app\model\User::changeInc(
                $plan->user_id,
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
            LoanRepaymentRecord::create([
                'plan_id' => $plan->id,
                'application_id' => $plan->application_id,
                'user_id' => $plan->user_id,
                'repayment_amount' => $req['repayment_amount'],
                'repayment_type' => $plan->overdue_days > 0 ? 3 : 1, // 逾期还款或正常还款
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

            Db::commit();
            return out(null, 0, "使用{$walletName}还款成功");
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '还款失败：' . $e->getMessage());
        }
    }

    /**
     * 发送逾期提醒
     */
    public function sendReminder()
    {
        $planId = request()->param('plan_id');
        
        $plan = LoanRepaymentPlan::with(['user', 'application'])->find($planId);
        if (!$plan) {
            return $this->error('还款计划不存在');
        }

        // 这里可以集成短信或邮件发送功能
        // 暂时只记录日志
        $message = "用户 {$plan->user->phone} 的贷款已逾期 {$plan->overdue_days} 天，应还金额：{$plan->remaining_amount}";
        
        // 记录提醒日志
        \think\facade\Log::info('逾期提醒：' . $message);

        return $this->success('提醒发送成功');
    }

    /**
     * 查看还款计划详情
     */
    public function showPlan()
    {
        $id = request()->param('id');
        
        $data = LoanRepaymentPlan::with(['user', 'application', 'repaymentRecords'])
            ->find($id);
        
        if (!$data) {
            return $this->error('还款计划不存在');
        }

        View::assign('data', $data);
        View::assign('statusMap', LoanRepaymentPlan::$statusMap);

        return View::fetch('loan_repayment/show_plan');
    }

    /**
     * 查看还款记录详情
     */
    public function showRecord()
    {
        $id = request()->param('id');
        
        $data = LoanRepaymentRecord::with(['user', 'application', 'plan'])
            ->find($id);
        
        if (!$data) {
            return $this->error('还款记录不存在');
        }

        View::assign('data', $data);
        View::assign('repaymentTypeMap', LoanRepaymentRecord::$repaymentTypeMap);
        View::assign('repaymentMethodMap', LoanRepaymentRecord::$repaymentMethodMap);

        return View::fetch('loan_repayment/show_record');
    }

    /**
     * 检查逾期（定时任务）
     */
    public function checkOverdue()
    {
        $today = date('Y-m-d');
        
        // 查找已逾期但状态未更新的还款计划
        $overduePlans = LoanRepaymentPlan::where('due_date', '<', $today)
            ->where('status', 1)
            ->select();

        $count = 0;
        foreach ($overduePlans as $plan) {
            $overdueDays = (strtotime($today) - strtotime($plan->due_date)) / 86400;
            
            $plan->status = 3; // 逾期
            $plan->overdue_days = $overdueDays;
            
            // 计算逾期利息
            $application = LoanApplication::find($plan->application_id);
            if ($application) {
                $product = $application->product;
                // 使用bcmath确保精度，计算过程中不四舍五入，最后结果才保留两位小数
                $overdueInterest = bcmul(
                    bcmul(
                        (string)$plan->remaining_amount, 
                        bcdiv($product->overdue_interest_rate, '100', 8), 
                        8
                    ), 
                    (string)$overdueDays, 
                    2
                );
                $plan->overdue_interest = $overdueInterest;
            }
            
            $plan->save();
            $count++;
        }

        return $this->success("检查完成，更新了 {$count} 条逾期记录");
    }
}

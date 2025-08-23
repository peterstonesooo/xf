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
        
        $data = $builder->paginate(['query' => $req])->each(function ($item, $key) {
            $item->status_text = $item->getStatusTextAttr(null, $item->toArray());
            return $item;
        });

        View::assign('req', $req);
        View::assign('data', $data);

        return View::fetch('loan_repayment/overdue_list');
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
            'repayment_method' => 'require|in:1,2',
            'remark' => 'max:500'
        ]);

        $plan = LoanRepaymentPlan::find($req['plan_id']);
        if (!$plan) {
            return $this->error('还款计划不存在');
        }

        if ($plan->status == 2) {
            return $this->error('该期已还款');
        }

        if ($req['repayment_amount'] > $plan->remaining_amount) {
            return $this->error('还款金额不能大于剩余金额');
        }

        Db::startTrans();
        try {
            // 创建还款记录
            LoanRepaymentRecord::create([
                'plan_id' => $plan->id,
                'application_id' => $plan->application_id,
                'user_id' => $plan->user_id,
                'repayment_amount' => $req['repayment_amount'],
                'repayment_type' => $plan->overdue_days > 0 ? 3 : 1, // 逾期还款或正常还款
                'repayment_method' => $req['repayment_method'],
                'remark' => $req['remark'] ?? ''
            ]);

            // 更新还款计划
            $plan->paid_amount += $req['repayment_amount'];
            $plan->remaining_amount = $plan->total_amount - $plan->paid_amount;
            
            if ($plan->remaining_amount <= 0) {
                $plan->status = 2; // 已还款
            }
            
            $plan->save();

            Db::commit();
            return $this->success('还款成功');
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('还款失败：' . $e->getMessage());
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
                $overdueInterest = $plan->remaining_amount * ($product->overdue_interest_rate / 100) * $overdueDays;
                $plan->overdue_interest = $overdueInterest;
            }
            
            $plan->save();
            $count++;
        }

        return $this->success("检查完成，更新了 {$count} 条逾期记录");
    }
}

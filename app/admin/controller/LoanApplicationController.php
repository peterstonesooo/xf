<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\LoanApplication;
use app\model\LoanProduct;
use app\model\LoanProductGradient;
use app\model\LoanRepaymentPlan;
use app\model\User;
use think\facade\Db;
use think\facade\View;

class LoanApplicationController extends AuthController
{
    /**
     * 申请列表
     */
    public function applicationList()
    {
        $req = request()->param();
        
        $builder = LoanApplication::with(['user', 'product', 'gradient', 'auditUser']);
        
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
        if (!empty($req['product_id'])) {
            $builder->where('product_id', $req['product_id']);
        }
        
        $builder->order('id desc');
        
        $data = $builder->paginate(['query' => $req])->each(function ($item, $key) {
            $item->status_text = $item->getStatusTextAttr(null, $item->toArray());
            return $item;
        });

        // 获取产品列表用于搜索
        $products = LoanProduct::where('status', 1)->select();

        View::assign('req', $req);
        View::assign('data', $data);
        View::assign('statusMap', LoanApplication::$statusMap);
        View::assign('products', $products);

        return View::fetch('loan_application/application_list');
    }

    /**
     * 申请详情
     */
    public function applicationDetail()
    {
        $id = request()->param('id');
        
        $data = LoanApplication::with(['user', 'product', 'gradient', 'auditUser', 'repaymentPlans'])
            ->find($id);
        
        if (!$data) {
            return $this->error('申请不存在');
        }

        View::assign('data', $data);
        View::assign('statusMap', LoanApplication::$statusMap);

        return View::fetch('loan_application/application_detail');
    }

    /**
     * 审核申请
     */
    public function auditApplication()
    {
        $req = request()->param();
        
        $this->validate($req, [
            'id' => 'require|number',
            'status' => 'require|in:2,3',
            'audit_remark' => 'max:500'
        ]);

        $application = LoanApplication::find($req['id']);
        if (!$application) {
            return $this->error('申请不存在');
        }

        if ($application->status != 1) {
            return $this->error('该申请已审核');
        }

        Db::startTrans();
        try {
            $application->status = $req['status'];
            $application->audit_user_id = session('admin_id');
            $application->audit_time = date('Y-m-d H:i:s');
            $application->audit_remark = $req['audit_remark'] ?? '';
            $application->save();

            // 如果审核通过，生成还款计划
            if ($req['status'] == 2) {
                $this->generateRepaymentPlan($application);
            }

            Db::commit();
            return $this->success('审核成功');
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('审核失败：' . $e->getMessage());
        }
    }

    /**
     * 批量审核
     */
    public function batchAudit()
    {
        $req = request()->param();
        
        $this->validate($req, [
            'ids' => 'require|array',
            'status' => 'require|in:2,3'
        ]);

        $applications = LoanApplication::whereIn('id', $req['ids'])
            ->where('status', 1)
            ->select();

        if ($applications->isEmpty()) {
            return $this->error('没有可审核的申请');
        }

        Db::startTrans();
        try {
            foreach ($applications as $application) {
                $application->status = $req['status'];
                $application->audit_user_id = session('admin_id');
                $application->audit_time = date('Y-m-d H:i:s');
                $application->save();

                // 如果审核通过，生成还款计划
                if ($req['status'] == 2) {
                    $this->generateRepaymentPlan($application);
                }
            }

            Db::commit();
            return $this->success('批量审核成功');
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('批量审核失败：' . $e->getMessage());
        }
    }

    /**
     * 放款
     */
    public function disburseLoan()
    {
        $id = request()->param('id');
        
        $application = LoanApplication::find($id);
        if (!$application) {
            return $this->error('申请不存在');
        }

        if ($application->status != 2) {
            return $this->error('该申请未通过审核');
        }

        if ($application->status == 4) {
            return $this->error('该申请已放款');
        }

        Db::startTrans();
        try {
            $application->status = 4;
            $application->disburse_time = date('Y-m-d H:i:s');
            $application->save();



            Db::commit();
            return $this->success('放款成功');
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('放款失败：' . $e->getMessage());
        }
    }

    /**
     * 导出申请
     */
    public function exportApplications()
    {
        $req = request()->param();
        
        $builder = LoanApplication::with(['user', 'product', 'gradient', 'auditUser']);
        
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
        
        $data = $builder->order('id desc')->select();

        // 导出Excel
        $filename = '贷款申请_' . date('YmdHis') . '.xls';
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo "ID\t用户ID\t手机号\t产品名称\t贷款金额\t贷款期限\t分期数\t利息率\t总利息\t总金额\t状态\t申请时间\t审核时间\t审核人\n";
        
        foreach ($data as $item) {
            echo $item->id . "\t";
            echo $item->user_id . "\t";
            echo $item->user->phone . "\t";
            echo $item->product->name . "\t";
            echo $item->loan_amount . "\t";
            echo $item->loan_days . "\t";
            echo $item->installment_count . "\t";
            echo $item->interest_rate . "\t";
            echo $item->total_interest . "\t";
            echo $item->total_amount . "\t";
            echo $item->getStatusTextAttr(null, $item->toArray()) . "\t";
            echo $item->created_at . "\t";
            echo $item->audit_time . "\t";
            echo $item->auditUser->username ?? '' . "\n";
        }
        exit;
    }

    /**
     * 生成还款计划
     */
    private function generateRepaymentPlan($application)
    {
        $loanAmount = $application->loan_amount;
        $installmentCount = $application->installment_count;
        $totalAmount = $application->total_amount;
        
        // 计算每期金额
        $monthlyAmount = $totalAmount / $installmentCount;
        $monthlyPrincipal = $loanAmount / $installmentCount;
        $monthlyInterest = ($totalAmount - $loanAmount) / $installmentCount;
        
        // 生成还款计划
        for ($i = 1; $i <= $installmentCount; $i++) {
            $dueDate = date('Y-m-d', strtotime("+{$i} month"));
            
            LoanRepaymentPlan::create([
                'application_id' => $application->id,
                'user_id' => $application->user_id,
                'period' => $i,
                'due_date' => $dueDate,
                'principal' => $monthlyPrincipal,
                'interest' => $monthlyInterest,
                'total_amount' => $monthlyAmount,
                'paid_amount' => 0,
                'remaining_amount' => $monthlyAmount,
                'status' => 1
            ]);
        }
    }


}

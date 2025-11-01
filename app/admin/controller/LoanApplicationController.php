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
            // 使用子查询代替 whereHas，避免关联查询问题
            $userIds = Db::name('user')
                ->where('phone', 'like', '%' . $req['phone'] . '%')
                ->column('id');
            if (!empty($userIds)) {
                $builder->whereIn('user_id', $userIds);
            } else {
                $builder->where('user_id', -1); // 没有匹配的用户，返回空结果
            }
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

            // 审核通过后不再生成还款计划，改为放款时生成
            // 这样可以基于实际的放款时间（disburse_time）来计算还款日期

            Db::commit();
            return out(null, 0, '审核成功');
    } catch (\Exception $e) {
        Db::rollback();
        return out(null, 10001, '审核失败：' . $e->getMessage());
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

                // 审核通过后不再生成还款计划，改为放款时生成
                // 这样可以基于实际的放款时间（disburse_time）来计算还款日期
            }

            Db::commit();
            return out(null, 0, '批量审核成功');
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '批量审核失败：' . $e->getMessage());
        }
    }

    /**
     * 放款
     */
    public function disburseLoan()
    {
        $id = request()->param('id');
        
        $application = LoanApplication::with(['user'])->find($id);
        if (!$application) {
            return out(null, 10001, '申请不存在');
        }

        if ($application->status != 2) {
            return out(null, 10001, '该申请未通过审核');
        }

        if ($application->status == 4) {
            return out(null, 10001, '该申请已放款');
        }

        Db::startTrans();
        try {
            // 1. 更新申请状态和放款时间
            $application->status = 4;
            $application->disburse_time = date('Y-m-d H:i:s');
            $application->save();

            // 2. 生成还款计划（基于放款时间计算）
            $this->generateRepaymentPlan($application);

            // 3. 给用户账户增加贷款金额到普惠钱包
            $user = User::where('id', $application->user_id)->lock(true)->find();
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            // 使用changeInc方法增加用户余额并记录资金流水
            User::changeInc(
                $application->user_id, 
                $application->loan_amount, 
                'puhui',  // 普惠钱包
                107,  // 交易类型：贷款放款
                $application->id, 
                13, 
                '贷款放款', 
                0, 
                1
            );

            // 发送放款成功消息给用户
            $this->sendDisburseMessage($application);
            
            Db::commit();
            return out(null, 0, '放款成功，已到账' . $application->loan_amount . '元');
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '放款失败：' . $e->getMessage());
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
            echo number_format($item->interest_rate, 4) . "\t";
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
     * 发送放款成功消息给用户
     */
    private function sendDisburseMessage($application)
    {
        try {
            // 获取用户信息
            $user = User::find($application->user_id);
            if (!$user) {
                return;
            }

            // 创建消息内容
            $title = '贷款放款成功通知';
            $content = "尊敬的{$user['realname']}，您的贷款申请已放款成功！\n\n";
            $content .= "贷款详情：\n";
            $content .= "• 贷款金额：{$application->loan_amount}元\n";
            $content .= "• 贷款期限：{$application->loan_days}天\n";
            $content .= "• 分期数：{$application->installment_count}期\n";
            $content .= "• 月供金额：" . number_format($application->total_amount / $application->installment_count, 2) . "元\n";
            $content .= "• 放款时间：" . date('Y-m-d H:i:s') . "\n\n";
            $content .= "资金已到账到您的充值余额中，请注意按时还款。";

            // 创建消息
            $message = \app\model\NoticeMessage::create([
                'title' => $title,
                'content' => $content,
                'type' => 1 // 系统通知
            ]);

            // 为用户创建消息记录
            \app\model\NoticeMessageUser::create([
                'user_id' => $application->user_id,
                'message_id' => $message->id,
                'is_read' => 0,
                'read_time' => null
            ]);

            // 记录日志
            \think\facade\Log::info('放款消息发送成功', [
                'application_id' => $application->id,
                'user_id' => $application->user_id,
                'message_id' => $message->id
            ]);

        } catch (\Exception $e) {
            // 记录错误日志，但不影响放款流程
            \think\facade\Log::error('发送放款消息失败：' . $e->getMessage(), [
                'application_id' => $application->id,
                'user_id' => $application->user_id
            ]);
        }
    }

    /**
     * 创建放款日志
     */
    private function createDisburseLog($application)
    {
        // 记录放款操作日志
        $logData = [
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'loan_amount' => $application->loan_amount,
            'disburse_time' => date('Y-m-d H:i:s'),
            'admin_user_id' => session('admin_id'),
            'remark' => '贷款放款到账',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // 如果有放款日志表，可以记录到这里
        // Db::table('mp_loan_disburse_log')->insert($logData);
        
        // 或者记录到系统日志中
        \think\facade\Log::info('贷款放款', [
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'loan_amount' => $application->loan_amount,
            'admin_user_id' => session('admin_id')
        ]);
    }

    /**
     * 生成还款计划
     * 基于放款时间（disburse_time）计算还款日期
     */
    private function generateRepaymentPlan($application)
    {
        $loanAmount = $application->loan_amount;
        $installmentCount = $application->installment_count;
        $totalAmount = $application->total_amount;
        $loanDays = $application->loan_days;
        
        // 计算每期金额（使用bcmath确保精度）
        $monthlyAmount = bcdiv((string)$totalAmount, (string)$installmentCount, 2);
        $monthlyPrincipal = bcdiv((string)$loanAmount, (string)$installmentCount, 2);
        $monthlyInterest = bcdiv(bcsub((string)$totalAmount, (string)$loanAmount, 2), (string)$installmentCount, 2);
        
        // 确定基准时间：优先使用放款时间，如果没有则使用当前时间
        $baseTime = $application->disburse_time ?? date('Y-m-d H:i:s');
        
        // 生成还款计划
        for ($i = 1; $i <= $installmentCount; $i++) {
            // 根据梯度表中的loan_days计算还款时间（基于放款时间）
            // 第一次还款：放款时间 + loan_days
            // 第二次还款：放款时间 + loan_days * 2
            // 第三次还款：放款时间 + loan_days * 3
            $daysToAdd = $loanDays * $i;
            $dueDate = date('Y-m-d', strtotime("+{$daysToAdd} day", strtotime($baseTime)));
            
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

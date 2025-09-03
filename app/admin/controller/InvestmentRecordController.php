<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\InvestmentRecord;
use app\model\InvestmentGradient;
use app\model\User;
use think\facade\View;

class InvestmentRecordController extends AuthController
{
    /**
     * 出资申请记录列表
     */
    public function recordList()
    {
        $req = request()->param();
        
        $builder = InvestmentRecord::alias('ir');
        
        // 搜索条件
        if (!empty($req['user_id'])) {
            $builder->where('ir.user_id', $req['user_id']);
        }
        if (!empty($req['phone'])) {
            // 修复 whereHas 查询问题，改用 JOIN 查询
            $builder->join('user u', 'ir.user_id = u.id')
                   ->where('u.phone', 'like', '%' . $req['phone'] . '%');
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('ir.status', $req['status']);
        }
        if (!empty($req['wallet_type'])) {
            $builder->where('ir.wallet_type', $req['wallet_type']);
        }
        if (!empty($req['gradient_id'])) {
            $builder->where('ir.gradient_id', $req['gradient_id']);
        }
        
        $builder->order('ir.id desc');
        
        $data = $builder->paginate(['query' => $req])->each(function ($item, $key) {
            $item->status_text = $item->getStatusTextAttr(null, $item->toArray());
            $item->wallet_type_text = $item->getWalletTypeTextAttr(null, $item->toArray());
            
            // 手动获取用户和梯度信息，因为使用了 JOIN 查询
            $item->user = User::find($item->user_id);
            $item->gradient = InvestmentGradient::find($item->gradient_id);
            
            return $item;
        });

        // 获取梯度列表用于搜索
        $gradients = InvestmentGradient::where('status', 1)->select();

        View::assign('req', $req);
        View::assign('data', $data);
        View::assign('statusMap', InvestmentRecord::$statusMap);
        View::assign('walletTypeMap', InvestmentRecord::$walletTypeMap);
        View::assign('gradients', $gradients);

        return View::fetch('investment_record/record_list');
    }

    /**
     * 出资申请记录详情
     */
    public function recordDetail()
    {
        $id = request()->param('id');
        
        $data = InvestmentRecord::with(['user', 'gradient', 'returnRecords'])
            ->find($id);
        
        if (!$data) {
            return $this->error('记录不存在');
        }

        View::assign('data', $data);
        View::assign('statusMap', InvestmentRecord::$statusMap);
        View::assign('walletTypeMap', InvestmentRecord::$walletTypeMap);

        return View::fetch('investment_record/record_detail');
    }

    /**
     * 导出出资申请记录
     */
    public function exportRecords()
    {
        $req = request()->param();
        
        $builder = InvestmentRecord::alias('ir');
        
        // 搜索条件
        if (!empty($req['user_id'])) {
            $builder->where('ir.user_id', $req['user_id']);
        }
        if (!empty($req['phone'])) {
            // 修复 whereHas 查询问题，改用 JOIN 查询
            $builder->join('user u', 'ir.user_id = u.id')
                   ->where('u.phone', 'like', '%' . $req['phone'] . '%');
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('ir.status', $req['status']);
        }
        if (!empty($req['wallet_type'])) {
            $builder->where('ir.wallet_type', $req['wallet_type']);
        }
        if (!empty($req['gradient_id'])) {
            $builder->where('ir.gradient_id', $req['gradient_id']);
        }
        
        $builder->order('ir.id desc');
        
        $data = $builder->select()->each(function ($item, $key) {
            $item->status_text = $item->getStatusTextAttr(null, $item->toArray());
            $item->wallet_type_text = $item->getWalletTypeTextAttr(null, $item->toArray());
            return $item;
        });

        // 导出Excel
        $filename = '出资申请记录_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // 输出CSV头部
        echo "ID,用户ID,用户手机号,用户姓名,出资金额,钱包类型,梯度名称,出资天数,总利息率,状态,创建时间\n";
        
        foreach ($data as $item) {
            // 由于使用了 JOIN 查询，需要手动获取用户和梯度信息
            $user = User::find($item->user_id);
            $gradient = InvestmentGradient::find($item->gradient_id);
            
            echo sprintf(
                "%d,%d,%s,%s,%.2f,%s,%s,%d,%.2f%%,%s,%s\n",
                $item->id,
                $item->user_id,
                $user ? $user->phone : '',
                $user ? $user->realname : '',
                $item->investment_amount,
                $item->wallet_type_text,
                $gradient ? $gradient->name : '',
                $gradient ? $gradient->investment_days : 0,
                number_format(($gradient ? $gradient->interest_rate : 0) * 100, 4),
                $item->status_text,
                $item->created_at
            );
        }
        exit;
    }
}




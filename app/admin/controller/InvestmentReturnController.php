<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\InvestmentReturnRecord;
use app\model\InvestmentRecord;
use app\model\User;
use think\facade\View;

class InvestmentReturnController extends AuthController
{
    /**
     * 出资返还记录列表
     */
    public function returnList()
    {
        $req = request()->param();
        
        $builder = InvestmentReturnRecord::with(['user', 'investment']);
        
        // 搜索条件
        if (!empty($req['user_id'])) {
            $builder->where('user_id', $req['user_id']);
        }
        if (!empty($req['phone'])) {
            $builder->whereHas('user', function($query) use ($req) {
                $query->where('phone', 'like', '%' . $req['phone'] . '%');
            });
        }
        if (!empty($req['return_type'])) {
            $builder->where('return_type', $req['return_type']);
        }
        if (!empty($req['wallet_type'])) {
            $builder->where('wallet_type', $req['wallet_type']);
        }
        if (!empty($req['investment_id'])) {
            $builder->where('investment_id', $req['investment_id']);
        }
        
        $builder->order('id desc');
        
        $data = $builder->paginate(['query' => $req])->each(function ($item, $key) {
            $item->return_type_text = $item->getReturnTypeTextAttr(null, $item->toArray());
            $item->wallet_type_text = $item->getWalletTypeTextAttr(null, $item->toArray());
            return $item;
        });

        View::assign('req', $req);
        View::assign('data', $data);
        View::assign('returnTypeMap', InvestmentReturnRecord::$returnTypeMap);
        View::assign('walletTypeMap', InvestmentReturnRecord::$walletTypeMap);

        return View::fetch('investment_return/return_list');
    }

    /**
     * 出资返还记录详情
     */
    public function returnDetail()
    {
        $id = request()->param('id');
        
        $data = InvestmentReturnRecord::with(['user', 'investment'])
            ->find($id);
        
        if (!$data) {
            return $this->error('记录不存在');
        }

        View::assign('data', $data);
        View::assign('returnTypeMap', InvestmentReturnRecord::$returnTypeMap);
        View::assign('walletTypeMap', InvestmentReturnRecord::$walletTypeMap);

        return View::fetch('investment_return/return_detail');
    }

    /**
     * 导出出资返还记录
     */
    public function exportReturns()
    {
        $req = request()->param();
        
        $builder = InvestmentReturnRecord::with(['user', 'investment']);
        
        // 搜索条件
        if (!empty($req['user_id'])) {
            $builder->where('user_id', $req['user_id']);
        }
        if (!empty($req['phone'])) {
            $builder->whereHas('user', function($query) use ($req) {
                $query->where('phone', 'like', '%' . $req['phone'] . '%');
            });
        }
        if (!empty($req['return_type'])) {
            $builder->where('return_type', $req['return_type']);
        }
        if (!empty($req['wallet_type'])) {
            $builder->where('wallet_type', $req['wallet_type']);
        }
        if (!empty($req['investment_id'])) {
            $builder->where('investment_id', $req['investment_id']);
        }
        
        $builder->order('id desc');
        
        $data = $builder->select()->each(function ($item, $key) {
            $item->return_type_text = $item->getReturnTypeTextAttr(null, $item->toArray());
            $item->wallet_type_text = $item->getWalletTypeTextAttr(null, $item->toArray());
            return $item;
        });

        // 导出Excel
        $filename = '出资返还记录_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // 输出CSV头部
        echo "ID,用户ID,用户手机号,用户姓名,出资记录ID,返还本金,返还利息,返还类型,钱包类型,返还时间\n";
        
        foreach ($data as $item) {
            echo sprintf(
                "%d,%d,%s,%s,%d,%.2f,%.2f,%s,%s,%s\n",
                $item->id,
                $item->user_id,
                $item->user->phone ?? '',
                $item->user->realname ?? '',
                $item->investment_id,
                $item->return_principal,
                $item->return_interest,
                $item->return_type_text,
                $item->wallet_type_text,
                $item->created_at
            );
        }
        exit;
    }
}

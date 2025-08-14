<?php

namespace app\admin\controller;

use app\model\User;
use app\model\UserBalanceLog;

class UserOpenController extends AuthController
{
    public function userBalanceLogList()
    {
        $req = request()->param();

        //$req['log_type'] = 1;
        $builder = UserBalanceLog::order('id', 'desc');
        if (isset($req['user_balance_log_id']) && $req['user_balance_log_id'] !== '') {
            $builder->where('id', $req['user_balance_log_id']);
        }
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('user_id', $user_ids);
        }
        if (isset($req['type']) && $req['type'] !== '') {
            $builder->where('type', $req['type']);
        } else {
            $builder->where('type', 49);
            $req['type'] = 49;
        }

        if (!empty($req['start_date'])) {
            $builder->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        $builder->group('user_id');

        $data = $builder->paginate(['query' => $req]);

        $count = $builder->count();
        if($req['type'] == 49) {
            $sum_amount = $count * 980;
        } else {
            $sum_amount = $count * 236;
        }
        
        $this->assign('count', $count);
        $this->assign('sum_amount', $sum_amount);
        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function userIntegralLogList()
    {
        $req = request()->param();

        $req['log_type'] = 2;
        $data = $this->logList($req);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }


}

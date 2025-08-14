<?php

namespace app\admin\controller;

use app\model\User;
use app\model\UserBalanceLog;

class UserBalanceLogController extends AuthController
{
    public function userBalanceLogList()
    {
        $req = request()->param();

        //$req['log_type'] = 1;
        $data = $this->logList($req);
        $typeMap = config('map.user_balance_log.balance_type_map');
        foreach($data as &$item){
            $item['type_text'] = $typeMap[$item['type']];
        }

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

    private function logList($req)
    {
        $builder = UserBalanceLog::order('id', 'desc');
        $builder->where('type', '<>', 24);
        $builder->where('type', '<>', 25);
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
        }
        if (isset($req['log_type']) && $req['log_type'] !== '') {
            $builder->where('log_type', $req['log_type']);
        }
        if (isset($req['relation_id']) && $req['relation_id'] !== '') {
            $builder->where('relation_id', $req['relation_id']);
        }

        if (!empty($req['start_date'])) {
            $builder->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        $data = $builder->paginate(['query' => $req]);

        return $data;
    }
}

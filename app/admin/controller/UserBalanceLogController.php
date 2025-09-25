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

    /**
     * 导出用户资金明细Excel
     */
    public function exportUserBalanceLog()
    {
        $req = request()->param();
        
        // 使用相同的查询条件获取数据
        $builder = UserBalanceLog::with(['user', 'adminUser'])->order('id', 'desc');
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

        $data = $builder->select();
        $typeMap = config('map.user_balance_log.balance_type_map');
        
        // 处理数据
        foreach ($data as &$item) {
            $item['type_text'] = $typeMap[$item['type']] ?? '';
            $item['user_phone'] = $item['user']['phone'] ?? '';
            $item['admin_nickname'] = $item['adminUser']['nickname'] ?? '';
        }

        // 使用通用的Excel导出函数
        create_excel($data, [
            'id' => 'ID',
            'user_phone' => '会员手机号',
            'type_text' => '类型',
            'before_balance' => '变化前余额',
            'change_balance' => '变动金额',
            'after_balance' => '变化后余额',
            'remark' => '备注',
            'relation_id' => '关联ID',
            'admin_nickname' => '后台用户',
            'created_at' => '创建时间'
        ], '用户资金明细_' . date('YmdHis'));
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

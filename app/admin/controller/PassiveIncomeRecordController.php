<?php

namespace app\admin\controller;

use app\model\PassiveIncomeRecord;

class PassiveIncomeRecordController extends AuthController
{
    public function passiveIncomeRecordList()
    {
        $req = request()->param();

        $builder = PassiveIncomeRecord::order('id', 'desc');
        if (isset($req['passive_income_record_id']) && $req['passive_income_record_id'] !== ''){
$builder->where('id', $req['passive_income_record_id']);
}
if (isset($req['user_id']) && $req['user_id'] !== ''){
$builder->where('user_id', $req['user_id']);
}
if (isset($req['order_id']) && $req['order_id'] !== ''){
$builder->where('order_id', $req['order_id']);
}
if (isset($req['status']) && $req['status'] !== ''){
$builder->where('status', $req['status']);
}
if (isset($req['execute_day']) && $req['execute_day'] !== ''){
$builder->where('execute_day', $req['execute_day']);
}

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showPassiveIncomeRecord()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])){
            $data = PassiveIncomeRecord::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addPassiveIncomeRecord()
    {
        $req = $this->validate(request(), [
            'user_id|用户ID' => 'require|number',
			'order_id|订单ID' => 'require|number',
			'amount|金额' => 'require|float',
			'days|天数' => 'require|integer',
			'status|状态' => 'require|integer',
			'is_finish|是否结束收益' => 'require|integer',
			'execute_day|执行日期' => 'require|integer',
        ]);

        PassiveIncomeRecord::create($req);

        return out();
    }

    public function editPassiveIncomeRecord()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'user_id|用户ID' => 'require|number',
			'order_id|订单ID' => 'require|number',
			'amount|金额' => 'require|float',
			'days|天数' => 'require|integer',
			'status|状态' => 'require|integer',
			'is_finish|是否结束收益' => 'require|integer',
			'execute_day|执行日期' => 'require|integer',
        ]);

        PassiveIncomeRecord::where('id', $req['id'])->update($req);

        return out();
    }

    public function changePassiveIncomeRecord()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        PassiveIncomeRecord::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    public function delPassiveIncomeRecord()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        PassiveIncomeRecord::destroy($req['id']);

        return out();
    }
}

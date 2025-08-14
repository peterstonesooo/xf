<?php

namespace app\model;

use think\Model;

class PassiveIncomeRecord extends Model
{
    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.passive_income_record')['status_map'];
        return $map[$data['status']];
    }

    public function getIsFinishTextAttr($value, $data)
    {
        $map = config('map.passive_income_record')['is_finish_map'];
        return $map[$data['is_finish']];
    }

    public function orders()
    {
        return $this->belongsTo(Order::class)->field('id,project_name,status,daily_bonus_ratio,single_amount,buy_num,order_sn');
    }
}

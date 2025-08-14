<?php

namespace app\model;

use think\Model;

class SubsidyIncomeRecord extends Model
{
	public function orders()
    {
        return $this->belongsTo(Order::class)->field('id,project_name,status,daily_bonus_ratio,single_amount,buy_num');
    }
}
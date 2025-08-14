<?php

namespace app\model;

use think\Model;

class UserCardRecord extends Model
{

    const cardStatus = [
        1 => '办卡中',
        2 => '办理成功',
        3 => '分发中',
        4 => '配送中',
        5 => '配送完成'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function syCard()
    {
        return $this->belongsTo(SyCard::class, 'card_id')->field('id,title');
    }

    public function getCardStatusTextAttr($value, $data)
    {
        return self::cardStatus[$data['status']] ?? '状态未知';
    }

}

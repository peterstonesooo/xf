<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class InvestmentRecord extends Model
{
    protected $name = 'investment_record';
    protected $pk = 'id';

    // 状态映射
    public static $statusMap = [
        1 => '进行中',
        2 => '已到期',
        3 => '已返还'
    ];

    // 钱包类型映射
    public static $walletTypeMap = [
        1 => '充值余额',
        2 => '荣誉钱包',
        3 => '稳盈钱包',
        4 => '民生钱包',
        5 => '惠民钱包',
        6 => '积分',
        7 => '幸福收益',
        8 => '稳赢钱包转入',
        9 => '抽奖卷',
        10 => '体验钱包预支金',
        11 => '体验钱包',
        12 => '幸福助力卷'
    ];

    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        return self::$statusMap[$data['status']] ?? '未知';
    }

    // 获取钱包类型文本
    public function getWalletTypeTextAttr($value, $data)
    {
        return self::$walletTypeMap[$data['wallet_type']] ?? '未知';
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // 关联梯度
    public function gradient()
    {
        return $this->belongsTo(InvestmentGradient::class, 'gradient_id', 'id');
    }

    // 关联返还记录
    public function returnRecords()
    {
        return $this->hasMany(InvestmentReturnRecord::class, 'investment_id', 'id');
    }
}

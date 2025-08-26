<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class InvestmentReturnRecord extends Model
{
    protected $name = 'investment_return_record';
    protected $pk = 'id';

    // 返还类型映射
    public static $returnTypeMap = [
        1 => '到期返还',
        2 => '提前返还'
    ];

    // 钱包类型映射
    public static $walletTypeMap = [
        1 => '充值余额',
        2 => '荣誉钱包',
        3 => '稳盈钱包',
        4 => '民生钱包',
        5 => '收益钱包',
        6 => '积分',
        7 => '幸福收益',
        8 => '稳赢钱包转入',
        9 => '抽奖卷',
        10 => '体验钱包预支金',
        11 => '体验钱包',
        12 => '幸福助力卷'
    ];

    // 获取返还类型文本
    public function getReturnTypeTextAttr($value, $data)
    {
        return self::$returnTypeMap[$data['return_type']] ?? '未知';
    }

    // 获取钱包类型文本
    public function getWalletTypeTextAttr($value, $data)
    {
        return self::$walletTypeMap[$data['wallet_type']] ?? '未知';
    }

    // 关联出资记录
    public function investment()
    {
        return $this->belongsTo(InvestmentRecord::class, 'investment_id', 'id');
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

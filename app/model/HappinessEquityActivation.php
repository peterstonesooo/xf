<?php

namespace app\model;

use think\Model;
use think\facade\Db;

class HappinessEquityActivation extends Model
{
    protected $name = 'happiness_equity_activation';
    
    // 状态映射
    const STATUS_MAP = [
        1 => '已激活',
        2 => '已失效'
    ];
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        return self::STATUS_MAP[$data['status']] ?? '未知状态';
    }
    
    /**
     * 检查用户是否已激活幸福权益
     */
    public static function isActivated($userId)
    {
        return self::where('user_id', $userId)
                   ->where('status', 1)
                   ->find();
    }
    
    /**
     * 获取用户的激活记录
     */
    public static function getUserActivation($userId)
    {
        return self::where('user_id', $userId)
                   ->where('status', 1)
                   ->find();
    }
    
    /**
     * 创建激活记录
     */
    public static function createActivation($userId, $equityRate, $walletBalances, $totalBalance, $paymentAmount, $beforeTopupBalance, $afterTopupBalance)
    {
        $activationSn = 'HAPPY' . build_order_sn($userId);
        
        return self::create([
            'user_id' => $userId,
            'activation_sn' => $activationSn,
            'equity_rate' => $equityRate,
            'topup_balance' => $walletBalances['topup_balance'],
            'team_bonus_balance' => $walletBalances['team_bonus_balance'],
            'butie_balance' => $walletBalances['butie'],
            'balance' => $walletBalances['balance'],
            'digit_balance' => $walletBalances['digit_balance'],
            'total_balance' => $totalBalance,
            'payment_amount' => $paymentAmount,
            'before_topup_balance' => $beforeTopupBalance,
            'after_topup_balance' => $afterTopupBalance,
            'status' => 1
        ]);
    }
    
    /**
     * 失效激活记录
     */
    public static function deactivate($userId)
    {
        return self::where('user_id', $userId)
                   ->where('status', 1)
                   ->update(['status' => 2]);
    }
    
    /**
     * 获取用户的激活记录列表
     */
    public static function getUserLogs($userId, $page = 1, $limit = 10)
    {
        return self::where('user_id', $userId)
                   ->order('created_at', 'desc')
                   ->paginate($limit, false, ['page' => $page]);
    }
}

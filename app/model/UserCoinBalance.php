<?php

namespace app\model;

use think\Model;
use app\model\UserCoinBalanceLog;
use Exception;

class UserCoinBalance extends Model
{
    public static function changeBalance($userId, $coinId, $amount)
    {
        $balance = self::where('user_id', $userId)->where('coin_id', $coinId)->find();
        if (empty($balance)) {
            self::insert([
                'user_id' => $userId,
                'coin_id' => $coinId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return self::changeBalance($userId, $coinId, $amount);
        }
        if ($amount < 0 && $balance['balance'] - abs($amount) < 0) {
            throw new Exception("余额不足", 10001);
        }
        $after = bcadd($balance['balance'], $amount, 4);
        $updateStatus = self::where('id', $balance['id'])->where('version', $balance['version'])->data(['balance' => $after, 'version' => $balance['version'] + 1, 'updated_at' => date('Y-m-d H:i:s')])->update();
        if (!$updateStatus) {
            throw new Exception("失败", 10001);
        }
        UserCoinBalanceLog::insert([
            'user_id' => $userId,
            'coin_id' => $coinId,
            'amount' => $amount,
            'before' => $balance['balance'],
            'after' => $after,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
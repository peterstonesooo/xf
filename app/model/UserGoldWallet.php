<?php

namespace app\model;

use think\Model;

/**
 * 用户黄金钱包模型
 */
class UserGoldWallet extends Model
{
    protected $name = 'user_gold_wallet';
    
    // 设置字段信息
    protected $schema = [
        'id'                 => 'bigint',
        'user_id'            => 'bigint',
        'gold_balance'       => 'decimal',
        'frozen_balance'     => 'decimal',
        'cost_price'         => 'decimal',
        'total_buy_quantity' => 'decimal',
        'total_sell_quantity'=> 'decimal',
        'total_buy_amount'   => 'decimal',
        'total_sell_amount'  => 'decimal',
        'realized_profit'    => 'decimal',
        'total_fee'          => 'decimal',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    /**
     * 获取或创建用户黄金钱包
     * @param int $userId
     * @return UserGoldWallet
     */
    public static function getOrCreate($userId)
    {
        $wallet = self::where('user_id', $userId)->find();
        
        if (!$wallet) {
            $wallet = self::create([
                'user_id' => $userId,
                'gold_balance' => 0,
                'frozen_balance' => 0,
                'cost_price' => 0,
                'total_buy_quantity' => 0,
                'total_sell_quantity' => 0,
                'total_buy_amount' => 0,
                'total_sell_amount' => 0,
                'realized_profit' => 0,
                'total_fee' => 0,
            ]);
        }
        
        return $wallet;
    }
    
    /**
     * 系统奖励黄金（0成本买入）
     * @param int $userId 用户ID
     * @param float $quantity 黄金数量（克）
     * @param float $price 当前金价（元/克）
     * @return bool
     */
    public static function addRewardGold($userId, $quantity, $price)
    {
        $wallet = self::where('user_id', $userId)->find();
        if(!$wallet){
            // 更新钱包数据
            return self::create([
                'user_id' => $userId,
                'gold_balance' => $quantity,
                'cost_price' => $price,
                'total_buy_quantity' => $quantity,
                'total_buy_amount' => ($price * $quantity),
            ]);
        }else{
            // 计算新成本价（加权平均，奖励按当前金价计入成本）
            if ($wallet->gold_balance == 0) {
                $newCostPrice = $price;
            } else {
                $totalCost = $wallet->cost_price * $wallet->gold_balance;
                $newCost = $price * $quantity;
                $newCostPrice = ($totalCost + $newCost) / ($wallet->gold_balance + $quantity);
            }
            // 更新钱包数据
            $wallet->gold_balance += $quantity;
            $wallet->cost_price = $newCostPrice;
            $wallet->total_buy_quantity += $quantity;
            $wallet->total_buy_amount += ($price * $quantity); // 虽然是奖励，但也算买入
            
            return $wallet->save();
        }
       
    }
}


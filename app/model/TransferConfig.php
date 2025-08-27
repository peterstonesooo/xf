<?php

namespace app\model;

use think\Model;

class TransferConfig extends Model
{
    protected $name = 'transfer_config';
    protected $pk = 'id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 钱包类型映射
    public static $walletTypeMap = [
        1 => '收益钱包',
        2 => '荣誉钱包', 
        3 => '余额钱包'
    ];
    
    // 状态映射
    public static $statusMap = [
        0 => '禁用',
        1 => '启用'
    ];
    
    // 获取钱包类型文本
    public function getWalletTypeTextAttr($value, $data)
    {
        return self::$walletTypeMap[$data['wallet_type']] ?? '未知';
    }
    
    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        return self::$statusMap[$data['status']] ?? '未知';
    }
    
    /**
     * 获取指定钱包类型的配置（仅启用状态）
     * @param int $walletType 钱包类型 1-收益钱包 2-荣誉钱包 3-余额钱包
     * @return array|null
     */
    public static function getConfigByWalletType($walletType)
    {
        return self::where('wallet_type', $walletType)
                   ->where('status', 1)
                   ->find();
    }
    
    /**
     * 获取指定钱包类型的配置（不管状态）
     * @param int $walletType 钱包类型 1-收益钱包 2-荣誉钱包 3-余额钱包
     * @return array|null
     */
    public static function getConfigByWalletTypeAll($walletType)
    {
        return self::where('wallet_type', $walletType)->find();
    }
    
    /**
     * 检查转账是否允许
     * @param int $walletType 钱包类型
     * @param float $amount 转账金额
     * @return array ['allowed' => bool, 'message' => string]
     */
    public static function checkTransferAllowed($walletType, $amount)
    {
        // 先查询配置，不管状态如何
        $config = self::where('wallet_type', $walletType)->find();
        
        if (!$config) {
            return ['allowed' => false, 'message' => '该钱包类型转账功能未配置'];
        }
        
        if ($config['status'] == 0) {
            return ['allowed' => false, 'message' => '该钱包类型转账功能已禁用'];
        }
        
        if ($amount < $config['min_amount']) {
            return ['allowed' => false, 'message' => "最低转账金额为{$config['min_amount']}元"];
        }
        
        if ($amount > $config['max_amount']) {
            return ['allowed' => false, 'message' => "最高转账金额为{$config['max_amount']}元"];
        }
        
        return ['allowed' => true, 'message' => '转账允许'];
    }
}

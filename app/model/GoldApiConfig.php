<?php

namespace app\model;

use think\Model;

/**
 * 黄金API配置模型
 */
class GoldApiConfig extends Model
{
    protected $name = 'gold_api_config';
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'description' => 'string',
        'key'         => 'string',
        'val'         => 'string',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    /**
     * 配置键常量
     */
    const KEY_API_TOKEN = 'api_token';           // API Token
    const KEY_API_URL = 'api_url';               // API地址
    const KEY_GOLD_CODE = 'gold_code';           // 黄金产品代码
    const KEY_SYNC_INTERVAL = 'sync_interval';   // 同步间隔（秒）
    const KEY_KLINE_TYPES = 'kline_types';       // 需要同步的K线类型（逗号分隔）
    const KEY_PRICE_TYPE = 'price_type';         // 价格类型（CNY/USD）
    const KEY_IS_ENABLED = 'is_enabled';         // 是否启用
    
    /**
     * 获取配置值
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getConfig($key, $default = null)
    {
        $config = self::where('key', $key)->find();
        return $config ? $config->val : $default;
    }
    
    /**
     * 设置配置值
     * @param string $key 配置键
     * @param string $val 配置值
     * @param string $description 描述
     * @return bool
     */
    public static function setConfig($key, $val, $description = '')
    {
        $config = self::where('key', $key)->find();
        
        if ($config) {
            return $config->save(['val' => $val]);
        } else {
            return self::create([
                'key'         => $key,
                'val'         => $val,
                'description' => $description,
            ]) !== false;
        }
    }
    
    /**
     * 获取所有配置（以键值对形式返回）
     * @return array
     */
    public static function getAllConfig()
    {
        $configs = self::select();
        $result = [];
        foreach ($configs as $config) {
            $result[$config->key] = $config->val;
        }
        return $result;
    }
}


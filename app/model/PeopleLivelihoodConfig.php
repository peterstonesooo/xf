<?php

namespace app\model;

use think\Model;

class PeopleLivelihoodConfig extends Model
{
    protected $name = 'people_livelihood_config';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    /**
     * 获取所有启用的配置
     * @return array
     */
    public static function getEnabledConfigs()
    {
        return self::where('is_enabled', 1)
                   ->field('id, title, config_key, config_value, is_enabled')
                   ->order('id', 'asc')
                   ->select()
                   ->toArray();
    }
    
    /**
     * 根据配置键获取配置值
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getConfigValue($key, $default = null)
    {
        $config = self::where('config_key', $key)
                     ->where('is_enabled', 1)
                     ->find();
        if ($config) {
            return $config['config_value'];
        }
        return $default;
    }
}


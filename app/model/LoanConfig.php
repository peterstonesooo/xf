<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class LoanConfig extends Model
{
    protected $name = 'loan_config';
    protected $pk = 'id';
    
    // 允许写入的字段
    protected $allowField = ['config_key', 'config_value', 'config_desc', 'config_type', 'config_options', 'sort', 'is_show'];

    // 配置类型映射
    public static $configTypeMap = [
        'text' => '文本',
        'number' => '数字',
        'select' => '选择',
        'textarea' => '多行文本'
    ];

    // 是否显示映射
    public static $isShowMap = [
        0 => '隐藏',
        1 => '显示'
    ];

    // 获取配置类型文本
    public function getConfigTypeTextAttr($value, $data)
    {
        return self::$configTypeMap[$data['config_type']] ?? '未知';
    }

    // 获取是否显示文本
    public function getIsShowTextAttr($value, $data)
    {
        return self::$isShowMap[$data['is_show']] ?? '未知';
    }

    // 获取配置选项数组
    public function getConfigOptionsArrayAttr($value, $data)
    {
        if (!empty($data['config_options'])) {
            return json_decode($data['config_options'], true);
        }
        return [];
    }

    /**
     * 获取配置值
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getConfig($key, $default = null)
    {
        $config = self::where('config_key', $key)->find();
        if ($config) {
            return $config->config_value;
        }
        return $default;
    }

    /**
     * 设置配置值
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @return bool
     */
    public static function setConfig($key, $value)
    {
        $config = self::where('config_key', $key)->find();
        if ($config) {
            $config->config_value = $value;
            return $config->save();
        }
        return false;
    }

    /**
     * 批量获取配置
     * @param array $keys 配置键数组
     * @return array
     */
    public static function getConfigs($keys)
    {
        $configs = self::whereIn('config_key', $keys)->select();
        $result = [];
        foreach ($configs as $config) {
            $result[$config->config_key] = $config->config_value;
        }
        return $result;
    }
}

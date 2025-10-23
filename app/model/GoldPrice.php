<?php

namespace app\model;

use think\Model;

/**
 * 黄金价格原始数据模型
 */
class GoldPrice extends Model
{
    protected $name = 'gold_price';
    
    // 设置字段信息
    protected $schema = [
        'id'           => 'bigint',
        'price'        => 'decimal',
        'price_type'   => 'string',
        'timestamp'    => 'int',
        'date_time'    => 'datetime',
        'source'       => 'string',
        'api_provider' => 'string',
        'raw_data'     => 'text',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    /**
     * 数据来源常量
     */
    const SOURCE_HISTORY = 'history';   // 历史导入
    const SOURCE_REALTIME = 'realtime'; // 实时获取
    const SOURCE_MANUAL = 'manual';     // 手动录入
    
    /**
     * 价格类型常量
     */
    const PRICE_TYPE_CNY = 'CNY'; // 人民币/克
    const PRICE_TYPE_USD = 'USD'; // 美元/盎司
    
    /**
     * 批量插入价格数据（忽略重复）
     * @param array $data
     * @return bool
     */
    public static function batchInsertIgnore($data)
    {
        if (empty($data)) {
            return true;
        }
        
        try {
            // 使用原生SQL实现INSERT IGNORE
            $fields = array_keys($data[0]);
            $fieldStr = '`' . implode('`,`', $fields) . '`';
            
            $values = [];
            foreach ($data as $row) {
                $valueStr = [];
                foreach ($row as $value) {
                    if (is_null($value)) {
                        $valueStr[] = 'NULL';
                    } else {
                        $valueStr[] = "'" . addslashes($value) . "'";
                    }
                }
                $values[] = '(' . implode(',', $valueStr) . ')';
            }
            
            $sql = "INSERT IGNORE INTO `mp_gold_price` ({$fieldStr}) VALUES " . implode(',', $values);
            return self::execute($sql) !== false;
        } catch (\Exception $e) {
            \think\facade\Log::error('批量插入价格数据失败：' . $e->getMessage());
            return false;
        }
    }
}


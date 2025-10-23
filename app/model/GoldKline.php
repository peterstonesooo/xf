<?php

namespace app\model;

use think\Model;

/**
 * 黄金K线数据模型
 */
class GoldKline extends Model
{
    protected $name = 'gold_kline';
    
    // 设置字段信息
    protected $schema = [
        'id'             => 'bigint',
        'period'         => 'string',
        'price_type'     => 'string',
        'open_price'     => 'decimal',
        'high_price'     => 'decimal',
        'low_price'      => 'decimal',
        'close_price'    => 'decimal',
        'volume'         => 'bigint',
        'amount'         => 'decimal',
        'start_time'     => 'int',
        'end_time'       => 'int',
        'start_datetime' => 'datetime',
        'end_datetime'   => 'datetime',
        'data_count'     => 'int',
        'is_completed'   => 'int',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    /**
     * K线周期常量
     */
    const PERIOD_1MIN   = '1min';
    const PERIOD_5MIN   = '5min';
    const PERIOD_15MIN  = '15min';
    const PERIOD_30MIN  = '30min';
    const PERIOD_1HOUR  = '1hour';
    const PERIOD_2HOUR  = '2hour';
    const PERIOD_4HOUR  = '4hour';
    const PERIOD_1DAY   = '1day';
    const PERIOD_1WEEK  = '1week';
    const PERIOD_1MONTH = '1month';
    
    /**
     * API K线类型映射
     */
    public static $klineTypeMap = [
        1  => self::PERIOD_1MIN,
        2  => self::PERIOD_5MIN,
        3  => self::PERIOD_15MIN,
        4  => self::PERIOD_30MIN,
        5  => self::PERIOD_1HOUR,
        6  => self::PERIOD_2HOUR,
        7  => self::PERIOD_4HOUR,
        8  => self::PERIOD_1DAY,
        9  => self::PERIOD_1WEEK,
        10 => self::PERIOD_1MONTH,
    ];
    
    /**
     * 价格类型常量
     */
    const PRICE_TYPE_CNY = 'CNY'; // 人民币/克
    const PRICE_TYPE_USD = 'USD'; // 美元/盎司
    
    /**
     * 完成状态
     */
    const STATUS_INCOMPLETE = 0; // 进行中
    const STATUS_COMPLETED = 1;  // 已完成
    
    /**
     * 根据API类型获取周期名称
     * @param int $klineType
     * @return string
     */
    public static function getPeriodByType($klineType)
    {
        return self::$klineTypeMap[$klineType] ?? self::PERIOD_1DAY;
    }
    
    /**
     * 批量插入或更新K线数据
     * @param array $data
     * @return bool
     */
    public static function batchInsertOrUpdate($data)
    {
        if (empty($data)) {
            return true;
        }
        
        try {
            foreach ($data as $row) {
                // 查找是否存在
                $exists = self::where([
                    'period' => $row['period'],
                    'start_time' => $row['start_time'],
                    'price_type' => $row['price_type']
                ])->find();
                
                if ($exists) {
                    // 更新
                    $exists->save($row);
                } else {
                    // 插入
                    self::create($row);
                }
            }
            return true;
        } catch (\Exception $e) {
            \think\facade\Log::error('批量插入或更新K线数据失败：' . $e->getMessage());
            return false;
        }
    }
}


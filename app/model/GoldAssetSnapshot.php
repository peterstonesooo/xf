<?php

namespace app\model;

use think\Model;

/**
 * 黄金资产快照模型
 */
class GoldAssetSnapshot extends Model
{
    protected $name = 'gold_asset_snapshot';
    
    // 设置字段信息
    protected $schema = [
        'id'                => 'bigint',
        'user_id'           => 'bigint',
        'gold_balance'      => 'decimal',
        'cost_price'        => 'decimal',
        'market_price'      => 'decimal',
        'market_value'      => 'decimal',
        'total_cost'        => 'decimal',
        'unrealized_profit' => 'decimal',
        'realized_profit'   => 'decimal',
        'total_profit'      => 'decimal',
        'total_asset'       => 'decimal',
        'profit_rate'       => 'decimal',
        'snapshot_date'     => 'date',
        'snapshot_time'     => 'datetime',
        'created_at'        => 'datetime',
    ];
    
    // 只有created_at，没有updated_at
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;
    
    /**
     * 获取用户指定日期的快照
     * @param int $userId
     * @param string $date 格式：Y-m-d
     * @return GoldAssetSnapshot|null
     */
    public static function getUserSnapshot($userId, $date)
    {
        return self::where('user_id', $userId)
                   ->where('snapshot_date', $date)
                   ->find();
    }
    
    /**
     * 获取用户最新的快照
     * @param int $userId
     * @return GoldAssetSnapshot|null
     */
    public static function getUserLatestSnapshot($userId)
    {
        return self::where('user_id', $userId)
                   ->order('snapshot_date', 'desc')
                   ->find();
    }
    
    /**
     * 计算用户昨日收益
     * @param int $userId
     * @param string $today 今天日期，格式：Y-m-d，默认今天
     * @return array ['yesterday_profit' => float, 'today_asset' => float, 'yesterday_asset' => float]
     */
    public static function calculateYesterdayProfit($userId, $today = null)
    {
        if (!$today) {
            $today = date('Y-m-d');
        }
        
        $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
        
        // 获取今日快照
        $todaySnapshot = self::getUserSnapshot($userId, $today);
        
        // 获取昨日快照
        $yesterdaySnapshot = self::getUserSnapshot($userId, $yesterday);
        
        $todayAsset = $todaySnapshot ? floatval($todaySnapshot->total_asset) : 0;
        $yesterdayAsset = $yesterdaySnapshot ? floatval($yesterdaySnapshot->total_asset) : 0;
        
        return [
            'yesterday_profit' => $todayAsset - $yesterdayAsset,
            'today_asset' => $todayAsset,
            'yesterday_asset' => $yesterdayAsset,
            'today_date' => $today,
            'yesterday_date' => $yesterday,
        ];
    }
    
    /**
     * 获取用户指定日期范围的快照列表
     * @param int $userId
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public static function getUserSnapshotList($userId, $startDate, $endDate)
    {
        return self::where('user_id', $userId)
                   ->where('snapshot_date', '>=', $startDate)
                   ->where('snapshot_date', '<=', $endDate)
                   ->order('snapshot_date', 'asc')
                   ->select()
                   ->toArray();
    }
    
    /**
     * 批量计算每日收益（用于统计图表）
     * @param int $userId
     * @param int $days 最近N天，默认30天
     * @return array
     */
    public static function getDailyProfitChart($userId, $days = 30)
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $snapshots = self::getUserSnapshotList($userId, $startDate, $endDate);
        
        if (empty($snapshots)) {
            return [];
        }
        
        $result = [];
        $prevAsset = 0;
        
        foreach ($snapshots as $snapshot) {
            $dailyProfit = $prevAsset > 0 ? ($snapshot['total_asset'] - $prevAsset) : 0;
            
            $result[] = [
                'date' => $snapshot['snapshot_date'],
                'total_asset' => $snapshot['total_asset'],
                'daily_profit' => $dailyProfit,
                'total_profit' => $snapshot['total_profit'],
                'profit_rate' => $snapshot['profit_rate'],
            ];
            
            $prevAsset = $snapshot['total_asset'];
        }
        
        return $result;
    }
    
    /**
     * 关联用户表
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}


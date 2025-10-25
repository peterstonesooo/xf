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
     * 计算用户昨日收益（昨天那一天赚了多少钱）
     * @param int $userId
     * @return array ['yesterday_profit' => float, 'yesterday_asset' => float, 'before_yesterday_asset' => float]
     */
    public static function calculateYesterdayProfit($userId)
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $beforeYesterday = date('Y-m-d', strtotime('-2 day'));
        
        // 获取昨日快照
        $yesterdaySnapshot = self::getUserSnapshot($userId, $yesterday);
        $yesterdayAsset = $yesterdaySnapshot ? floatval($yesterdaySnapshot->total_asset) : 0;
        
        // 获取前日快照
        $beforeYesterdaySnapshot = self::getUserSnapshot($userId, $beforeYesterday);
        $beforeYesterdayAsset = $beforeYesterdaySnapshot ? floatval($beforeYesterdaySnapshot->total_asset) : 0;
        
        // 昨日收益 = 昨日总资产 - 前日总资产
        $yesterdayProfit = $yesterdayAsset - $beforeYesterdayAsset;
        
        return [
            'yesterday_profit' => $yesterdayProfit,              // 昨日收益
            'yesterday_asset' => $yesterdayAsset,                // 昨日总资产
            'before_yesterday_asset' => $beforeYesterdayAsset,   // 前日总资产
            'yesterday_date' => $yesterday,
            'before_yesterday_date' => $beforeYesterday,
        ];
    }
    
    /**
     * 计算用户今日收益（今天到目前为止赚了多少钱）
     * @param int $userId
     * @return array ['today_profit' => float, 'today_asset' => float, 'yesterday_asset' => float]
     */
    public static function calculateTodayProfit($userId)
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // 获取昨日快照
        $yesterdaySnapshot = self::getUserSnapshot($userId, $yesterday);
        $yesterdayAsset = $yesterdaySnapshot ? floatval($yesterdaySnapshot->total_asset) : 0;
        
        // 获取今日总资产（优先快照，无快照则实时计算）
        $todaySnapshot = self::getUserSnapshot($userId, $today);
        
        if ($todaySnapshot) {
            // 今日快照已生成（23:59后）
            $todayAsset = floatval($todaySnapshot->total_asset);
            $fromSnapshot = true;
        } else {
            // 今日快照未生成（23:59前），使用实时计算
            $todayAsset = self::calculateRealTimeAsset($userId);
            $fromSnapshot = false;
        }
        
        // 今日收益 = 今日总资产 - 昨日总资产
        $todayProfit = $todayAsset - $yesterdayAsset;
        
        return [
            'today_profit' => $todayProfit,          // 今日收益
            'today_asset' => $todayAsset,            // 今日总资产
            'yesterday_asset' => $yesterdayAsset,    // 昨日总资产
            'today_date' => $today,
            'yesterday_date' => $yesterday,
            'from_snapshot' => $fromSnapshot,        // 今日数据是否来自快照
            'is_realtime' => !$fromSnapshot,         // 今日数据是否实时计算
        ];
    }
    
    /**
     * 实时计算用户当前总资产
     * @param int $userId
     * @return float
     */
    private static function calculateRealTimeAsset($userId)
    {
        // 获取用户黄金钱包
        $wallet = \app\model\UserGoldWallet::where('user_id', $userId)->find();
        
        if (!$wallet || $wallet->gold_balance <= 0) {
            return 0;
        }
        
        // 获取当前实时金价
        $service = new \app\common\service\GoldKlineService();
        $priceResult = $service->getLatestPrice();
        $currentPrice = $priceResult['success'] ? $priceResult['price'] : 0;
        
        if ($currentPrice <= 0) {
            // 如果实时金价获取失败，降级使用K线最新收盘价
            $latestKline = \app\model\GoldKline::where('period', '1day')
                ->where('price_type', 'CNY')
                ->order('start_time', 'desc')
                ->find();
            
            $currentPrice = $latestKline ? floatval($latestKline->close_price) : 0;
        }
        
        // 计算总资产 = 黄金余额 × 当前金价
        $totalAsset = floatval($wallet->gold_balance) * $currentPrice;
        
        return $totalAsset;
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


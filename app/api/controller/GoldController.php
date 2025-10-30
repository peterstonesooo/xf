<?php

namespace app\api\controller;

use app\model\GoldKline;
use app\model\GoldPrice;
use app\model\UserGoldWallet;
use app\model\GoldAssetSnapshot;
use app\common\service\GoldKlineService;
use app\model\GoldApiConfig;

/**
 * 黄金K线数据控制器
 */
class GoldController extends AuthController
{
    /**
     * 获取K线数据
     * @return \think\response\Json
     */
    public function getKlineData()
    {
        try {
            $req = $this->validate(request(), [
                'type' => 'require|in:1,2,3,4,5',
            ]);
            
            $user = $this->user;
            $type = intval($req['type']);
            
            // 根据类型确定查询参数
            $params = $this->getQueryParams($type);
            
            // 查询K线数据
            $klineData = GoldKline::where('period', $params['period'])
                ->where('price_type', GoldPrice::PRICE_TYPE_CNY) // 默认使用人民币价格
                // ->where('is_completed', GoldKline::STATUS_COMPLETED) // 只返回已完成的K线
                ->order('start_time', 'desc')
                ->limit($params['limit'])
                ->select();
            
            if ($klineData->isEmpty()) {
                return out([], 200, '暂无K线数据');
            }
            
            // 反转数组，使其按时间正序排列
            $klineData = array_reverse($klineData->toArray());
            $golbConfig = GoldApiConfig::where('key', 'in',['gold_reserve','apply_gold_number','total_hold_gold'])->select();
            foreach($golbConfig as $item){
                $golbConfigData[$item['key']] = $item['val'];
            }
            // 格式化数据
            $formattedData = [];
            foreach ($klineData as $item) {
                $formattedData[] = [
                    'timestamp' => $item['start_time'],
                    'datetime' => $item['start_datetime'],
                    'open' => $item['open_price'],
                    'high' => $item['high_price'],
                    'low' => ($item['low_price']),
                    'close' => ($item['close_price']),
                ];
            }
            
            // 获取当前金价（使用缓存，15秒有效期）
            $service = new GoldKlineService();
            $priceResult = $service->getLatestPrice();
            $currentPrice = $priceResult['success'] ? $priceResult['price'] : 0;
            
            // 获取用户黄金钱包
            $goldWallet = UserGoldWallet::where('user_id', $user['id'])->find();
            
            // 初始化默认值
            $goldBalance = 0;           // 当前持有（克）
            $costPrice = 0;             // 成本价
            $holdEarning = 0;           // 持有收益（浮动盈亏）
            $totalEarning = 0;          // 累积收益（已实现盈亏）
            $yesterdayEarning = 0;      // 昨日收益（昨天那一天赚了多少）
            $todayEarning = 0;          // 今日收益（今天到目前赚了多少）
            
            if ($goldWallet) {
                $goldBalance = floatval($goldWallet->gold_balance);
                $costPrice = floatval($goldWallet->cost_price);
                
                // 计算持有收益 = (当前金价 - 成本价) × 持有数量
                if ($currentPrice > 0 && $goldBalance > 0) {
                    $holdEarning = ($currentPrice - $costPrice) * $goldBalance;
                }
                
                // 累积收益（已实现盈亏）
                $totalEarning = floatval($goldWallet->realized_profit);
                
                // 计算昨日收益（昨日总资产 - 前日总资产）
                $yesterdayProfit = GoldAssetSnapshot::calculateYesterdayProfit($user['id']);
                $yesterdayEarning = $yesterdayProfit['yesterday_profit'];
                
                // 计算今日收益（今日总资产 - 昨日总资产）
                $todayProfit = GoldAssetSnapshot::calculateTodayProfit($user['id']);
                $todayEarning = $todayProfit['today_profit'];
            }
            
            // 统计全局数据
            // 申领黄金人数（有黄金余额的用户数）
            $applyGoldNumber = UserGoldWallet::where('gold_balance', '>', 0)->count();
            
            // 累计持有黄金总量（所有用户）
            $totalHoldGold = UserGoldWallet::sum('gold_balance');
            
            // 黄金储备（当前用户的黄金余额）
            $goldReserve = $goldBalance;
            
            // 储备估值（黄金储备 × 当前金价）
            $goldReserveAmount = $goldBalance * $currentPrice;
            
            return out([
                'current_price' => round($currentPrice, 2),              // 当前金价（元/克）
                'gold_wallet' => round($goldBalance, 6),                 // 当前持有（克）
                'gold_wallet_price' => round($goldBalance*$currentPrice, 2),                 // 当前持有价格（元）
                'yesterday_earning' => round($yesterdayEarning, 2),      // 昨日收益（昨天那一天赚了多少，元）
                'today_earning' => round($todayEarning, 2),              // 今日收益（今天到目前赚了多少，元）
                'hold_earning' => round($holdEarning, 2),                // 持有收益/浮动盈亏（元）
                'total_earning' => round($holdEarning, 2),              // 累积收益/已实现盈亏（元）
                
                'gold_reserve' => $golbConfigData['gold_reserve'],                // 黄金储备（吨）
                'gold_reserve_amount' => $golbConfigData['gold_reserve']*$currentPrice*1000*1000,   // 储备估值（元）
                'apply_gold_number' => $golbConfigData['apply_gold_number'] + UserGoldWallet::count() + (date('m')*30*24*60+date('d')*24*60+date('H')*60+date('i')),                 // 申领黄金人数
                'total_hold_gold' => $golbConfigData['total_hold_gold'] +UserGoldWallet::sum('gold_balance') + (date('m')*30*24*60+date('d')*24*60+date('H')*60+date('i'))*2,           // 累计持有黄金[所有人]（克）
                
                'type' => $type,
                'type_name' => $params['name'],
                'period' => $params['period'],
                'count' => count($formattedData),
                'kline_list' => $formattedData
            ], 200, '获取成功');
            
        } catch (\Exception $e) {
            return out(null, 500, '获取K线数据失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取最新价格
     * @return \think\response\Json
     */
    public function getLatestPrice()
    {
        try {
            // 获取最新的1分钟K线数据
            $latestKline = GoldKline::where('period', GoldKline::PERIOD_1MIN)
                ->where('price_type', GoldPrice::PRICE_TYPE_CNY)
                ->order('start_time', 'desc')
                ->find();
            
            if (!$latestKline) {
                return out(null, 404, '暂无价格数据');
            }
            
            // 计算涨跌
            $prevKline = GoldKline::where('period', GoldKline::PERIOD_1DAY)
                ->where('price_type', GoldPrice::PRICE_TYPE_CNY)
                ->where('start_time', '<', $latestKline['start_time'])
                ->order('start_time', 'desc')
                ->find();
            
            $change = 0;
            $changePercent = 0;
            if ($prevKline) {
                $change = bcsub((string)$latestKline['close_price'], (string)$prevKline['close_price'], 2);
                $changePercent = bcmul(
                    bcdiv($change, (string)$prevKline['close_price'], 6),
                    '100',
                    2
                );
            }
            
            return out([
                'price' => floatval($latestKline['close_price']),
                'open' => floatval($latestKline['open_price']),
                'high' => floatval($latestKline['high_price']),
                'low' => floatval($latestKline['low_price']),
                'change' => floatval($change),
                'change_percent' => floatval($changePercent),
                'timestamp' => $latestKline['start_time'],
                'datetime' => $latestKline['start_datetime'],
                'volume' => intval($latestKline['volume']),
            ], 200, '获取成功');
            
        } catch (\Exception $e) {
            return out(null, 500, '获取价格失败：' . $e->getMessage());
        }
    }
    
    /**
     * 根据类型获取查询参数
     * @param int $type
     * @return array
     */
    private function getQueryParams($type)
    {
        $paramsMap = [
            1 => [
                'name' => '近一天分钟K线',
                'period' => GoldKline::PERIOD_1MIN,
                'limit' => 1440, // 1天 = 24小时 * 60分钟
            ],
            2 => [
                'name' => '近一个月日K线',
                'period' => GoldKline::PERIOD_1DAY,
                'limit' => 30,
            ],
            3 => [
                'name' => '近3个月日K线',
                'period' => GoldKline::PERIOD_1DAY,
                'limit' => 90,
            ],
            4 => [
                'name' => '近6个月日K线',
                'period' => GoldKline::PERIOD_1DAY,
                'limit' => 180,
            ],
            5 => [
                'name' => '近一年日K线',
                'period' => GoldKline::PERIOD_1DAY,
                'limit' => 365,
            ],
        ];
        
        return $paramsMap[$type];
    }
    
    /**
     * 获取K线统计数据
     * @return \think\response\Json
     */
    public function getKlineStats()
    {
        try {
            $stats = [];
            
            // 统计各周期K线数据量
            $periods = [
                GoldKline::PERIOD_1MIN => '1分钟',
                GoldKline::PERIOD_5MIN => '5分钟',
                GoldKline::PERIOD_15MIN => '15分钟',
                GoldKline::PERIOD_30MIN => '30分钟',
                GoldKline::PERIOD_1HOUR => '1小时',
                GoldKline::PERIOD_1DAY => '1天',
            ];
            
            foreach ($periods as $period => $name) {
                $count = GoldKline::where('period', $period)
                    ->where('price_type', GoldPrice::PRICE_TYPE_CNY)
                    ->count();
                
                $latest = GoldKline::where('period', $period)
                    ->where('price_type', GoldPrice::PRICE_TYPE_CNY)
                    ->order('start_time', 'desc')
                    ->find();
                
                $stats[] = [
                    'period' => $period,
                    'period_name' => $name,
                    'count' => $count,
                    'latest_time' => $latest ? $latest['start_datetime'] : null,
                ];
            }
            
            return out($stats, 200, '获取成功');
            
        } catch (\Exception $e) {
            return out(null, 500, '获取统计数据失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取实时金价（从Redis缓存或API）
     * @return \think\response\Json
     */
    public function getCurrentPrice()
    {
        try {
            $service = new GoldKlineService();
            
            // 获取最新金价（自动使用Redis缓存）
            $result = $service->getLatestPrice();
            
            if (!$result['success']) {
                return out(null, 500, $result['message']);
            }
            
            return out([
                'price' => $result['price'],
                'data' => $result['data'],
                'from_cache' => $result['from_cache'] ?? false,
            ], 200, '获取成功');
            
        } catch (\Exception $e) {
            return out(null, 500, '获取实时金价失败：' . $e->getMessage());
        }
    }
    
    /**
     * 强制刷新实时金价（跳过Redis缓存）
     * @return \think\response\Json
     */
    public function refreshCurrentPrice()
    {
        try {
            $service = new GoldKlineService();
            
            // 强制从API获取最新金价
            $result = $service->getLatestPrice(true);
            
            if (!$result['success']) {
                return out(null, 500, $result['message']);
            }
            
            return out([
                'price' => $result['price'],
                'data' => $result['data'],
                'from_cache' => false,
            ], 200, '刷新成功');
            
        } catch (\Exception $e) {
            return out(null, 500, '刷新实时金价失败：' . $e->getMessage());
        }
    }
}


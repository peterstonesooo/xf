<?php

namespace app\api\controller;

use app\model\GoldKline;
use app\model\GoldPrice;

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
            
            return out([
                'yestoday_earning' => 1,        //昨日收益
                'hold_earning' => 2,            //持有收益
                'total_earning' => 3,           //累积收益
                'gold_reserve' => 4,           //黄金储备
                'gold_reserve_amount' => 5,     //储备估值
                'apply_gold_number' => 6,       //申领黄金人数
                'total_hold_gold' => 7,         //累计持有黄金
                
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
}


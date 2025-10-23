<?php

namespace app\common\service;

use app\model\GoldPrice;
use app\model\GoldKline;
use app\model\GoldSyncLog;
use app\model\GoldApiConfig;
use think\facade\Log;

/**
 * 黄金K线数据服务类
 */
class GoldKlineService
{
    /**
     * API基础URL
     */
    private $apiUrl = 'https://quote.alltick.co/quote-b-api/kline';
    
    /**
     * API Token
     */
    private $apiToken;
    
    /**
     * 黄金产品代码
     */
    private $goldCode;
    
    /**
     * 价格类型
     */
    private $priceType = GoldPrice::PRICE_TYPE_CNY;
    
    /**
     * 盎司转克换算系数（1盎司 = 31.1035克）
     */
    const OZ_TO_GRAM = 31.1035;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        // 从配置表读取配置
        $this->apiToken = GoldApiConfig::getConfig(GoldApiConfig::KEY_API_TOKEN, '');
        $this->goldCode = GoldApiConfig::getConfig(GoldApiConfig::KEY_GOLD_CODE, 'XAUUSD');
        $this->priceType = GoldApiConfig::getConfig(GoldApiConfig::KEY_PRICE_TYPE, GoldPrice::PRICE_TYPE_CNY);
        
        $customApiUrl = GoldApiConfig::getConfig(GoldApiConfig::KEY_API_URL, '');
        if ($customApiUrl) {
            $this->apiUrl = $customApiUrl;
        }
    }
    
    /**
     * 同步历史K线数据
     * @param int $klineType K线类型（1-10）
     * @param int $queryNum 查询数量（每次最多500）
     * @param int $endTimestamp 结束时间戳（0表示当前时间）
     * @return array
     */
    public function syncHistoryKline($klineType = 8, $queryNum = 500, $endTimestamp = 0)
    {
        // 创建同步日志
        $log = GoldSyncLog::createLog(GoldSyncLog::TASK_HISTORY_IMPORT, [
            'kline_type' => $klineType,
            'query_num' => $queryNum,
            'end_timestamp' => $endTimestamp,
            'gold_code' => $this->goldCode,
            'api_provider' => 'alltick',
        ]);
        
        try {
            // 调用API获取K线数据
            $result = $this->fetchKlineFromApi($klineType, $queryNum, $endTimestamp);
            
            if (!$result['success']) {
                $log->markFailed($result['message']);
                return $result;
            }
            
            $klineList = $result['data']['kline_list'] ?? [];
            
            if (empty($klineList)) {
                $log->markSuccess(0, 0, 0);
                return [
                    'success' => true,
                    'message' => '没有新数据',
                    'data' => [
                        'success_count' => 0,
                        'fail_count' => 0,
                        'kline_list' => []
                    ]
                ];
            }
            
            // 保存K线数据到数据库
            $saveResult = $this->saveKlineData($klineList, $klineType);
            
            // 更新日志
            $log->markSuccess(
                count($klineList),
                $saveResult['success_count'],
                $saveResult['fail_count']
            );
            
            return [
                'success' => true,
                'message' => "同步成功，共{$saveResult['success_count']}条",
                'data' => [
                    'success_count' => $saveResult['success_count'],
                    'fail_count' => $saveResult['fail_count'],
                    'kline_list' => $klineList  // 返回原始K线数据，用于批量同步时获取时间戳
                ]
            ];
            
        } catch (\Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('同步历史K线失败：' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * 同步最新K线数据（用于实时更新）
     * @param array $klineTypes K线类型数组
     * @return array
     */
    public function syncLatestKline($klineTypes = [8])
    {
        // 创建同步日志
        $log = GoldSyncLog::createLog(GoldSyncLog::TASK_REALTIME_FETCH, [
            'kline_types' => $klineTypes,
            'gold_code' => $this->goldCode,
            'api_provider' => 'alltick',
        ]);
        
        try {
            $totalSuccess = 0;
            $totalFail = 0;
            $results = [];
            
            foreach ($klineTypes as $klineType) {
                // 获取最新2根K线
                $result = $this->fetchKlineFromApi($klineType, 2, 0);
                
                if (!$result['success']) {
                    $totalFail++;
                    $results[] = [
                        'kline_type' => $klineType,
                        'success' => false,
                        'message' => $result['message']
                    ];
                    continue;
                }
                
                $klineList = $result['data']['kline_list'] ?? [];
                
                if (!empty($klineList)) {
                    $saveResult = $this->saveKlineData($klineList, $klineType);
                    $totalSuccess += $saveResult['success_count'];
                    $totalFail += $saveResult['fail_count'];
                    
                    $results[] = [
                        'kline_type' => $klineType,
                        'success' => true,
                        'count' => $saveResult['success_count']
                    ];
                } else {
                    $results[] = [
                        'kline_type' => $klineType,
                        'success' => true,
                        'count' => 0
                    ];
                }
                
                // 避免请求过快，休眠1秒
                sleep(1);
            }
            
            $log->markSuccess(
                $totalSuccess + $totalFail,
                $totalSuccess,
                $totalFail
            );
            
            return [
                'success' => true,
                'message' => "同步完成，成功{$totalSuccess}条，失败{$totalFail}条",
                'data' => $results
            ];
            
        } catch (\Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('同步最新K线失败：' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * 从API获取K线数据
     * @param int $klineType K线类型
     * @param int $queryNum 查询数量
     * @param int $endTimestamp 结束时间戳
     * @return array
     */
    private function fetchKlineFromApi($klineType, $queryNum, $endTimestamp)
    {
        try {
            // 生成追踪码
            $trace = $this->generateTrace();
            
            // 构建查询参数
            $query = [
                'trace' => $trace,
                'data' => [
                    'code' => $this->goldCode,
                    'kline_type' => $klineType,
                    'kline_timestamp_end' => $endTimestamp,
                    'query_kline_num' => $queryNum,
                    'adjust_type' => 0
                ]
            ];
            
            // 构建完整URL
            $url = $this->apiUrl . '?token=' . $this->apiToken . '&query=' . urlencode(json_encode($query));
            
            Log::info('请求K线API：' . $url);
            
            // 发送HTTP请求
            $response = $this->httpGet($url);
            
            if (!$response) {
                return [
                    'success' => false,
                    'message' => 'API请求失败',
                    'data' => []
                ];
            }
            
            $result = json_decode($response, true);
            
            if (!$result || !isset($result['ret'])) {
                return [
                    'success' => false,
                    'message' => 'API返回数据格式错误',
                    'data' => []
                ];
            }
            
            // 检查返回码
            if ($result['ret'] != 200) {
                return [
                    'success' => false,
                    'message' => $result['msg'] ?? '未知错误',
                    'data' => []
                ];
            }
            
            return [
                'success' => true,
                'message' => 'ok',
                'data' => $result['data']
            ];
            
        } catch (\Exception $e) {
            Log::error('API请求异常：' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * 保存K线数据到数据库
     * @param array $klineList K线数据列表
     * @param int $klineType K线类型
     * @return array
     */
    private function saveKlineData($klineList, $klineType)
    {
        $successCount = 0;
        $failCount = 0;
        $period = GoldKline::getPeriodByType($klineType);
        
        foreach ($klineList as $kline) {
            try {
                $timestamp = intval($kline['timestamp']);
                $startTime = $timestamp;
                $endTime = $this->calculateEndTime($startTime, $period);
                
                // 价格转换：如果是人民币且产品是XAUCNH，需要将盎司价格转换为克价格
                $openPrice = $this->convertPrice($kline['open_price']);
                $highPrice = $this->convertPrice($kline['high_price']);
                $lowPrice = $this->convertPrice($kline['low_price']);
                $closePrice = $this->convertPrice($kline['close_price']);
                
                // 准备K线数据
                $klineData = [
                    'period' => $period,
                    'price_type' => $this->priceType,
                    'open_price' => $openPrice,
                    'high_price' => $highPrice,
                    'low_price' => $lowPrice,
                    'close_price' => $closePrice,
                    'volume' => $kline['volume'] ?? 0,
                    'amount' => $kline['turnover'] ?? 0,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'start_datetime' => date('Y-m-d H:i:s', $startTime),
                    'end_datetime' => date('Y-m-d H:i:s', $endTime),
                    'data_count' => 1,
                    'is_completed' => $endTime <= time() ? GoldKline::STATUS_COMPLETED : GoldKline::STATUS_INCOMPLETE,
                ];
                
                // 查找是否已存在
                $exists = GoldKline::where([
                    'period' => $period,
                    'start_time' => $startTime,
                    'price_type' => $this->priceType
                ])->find();
                
                if ($exists) {
                    // 更新
                    $exists->save($klineData);
                } else {
                    // 插入
                    GoldKline::create($klineData);
                }
                
                $successCount++;
                
            } catch (\Exception $e) {
                $failCount++;
                Log::error('保存K线数据失败：' . $e->getMessage());
            }
        }
        
        return [
            'success_count' => $successCount,
            'fail_count' => $failCount
        ];
    }
    
    /**
     * 转换价格（盎司转克）
     * @param string|float $price 原始价格
     * @return string
     */
    private function convertPrice($price)
    {
        // 如果是人民币价格类型，并且产品代码包含CNH，则需要从盎司转换为克
        if ($this->priceType === GoldPrice::PRICE_TYPE_CNY && 
            (stripos($this->goldCode, 'CNH') !== false || stripos($this->goldCode, 'CNY') !== false)) {
            // 1盎司 = 31.1035克，所以克价格 = 盎司价格 / 31.1035
            $price = floatval($price) / self::OZ_TO_GRAM;
        }
        
        return number_format($price, 2, '.', '');
    }
    
    /**
     * 计算K线结束时间
     * @param int $startTime 开始时间戳
     * @param string $period 周期
     * @return int
     */
    private function calculateEndTime($startTime, $period)
    {
        $seconds = [
            GoldKline::PERIOD_1MIN => 60,
            GoldKline::PERIOD_5MIN => 300,
            GoldKline::PERIOD_15MIN => 900,
            GoldKline::PERIOD_30MIN => 1800,
            GoldKline::PERIOD_1HOUR => 3600,
            GoldKline::PERIOD_2HOUR => 7200,
            GoldKline::PERIOD_4HOUR => 14400,
            GoldKline::PERIOD_1DAY => 86400,
            GoldKline::PERIOD_1WEEK => 604800,
            GoldKline::PERIOD_1MONTH => 2592000, // 30天
        ];
        
        return $startTime + ($seconds[$period] ?? 86400);
    }
    
    /**
     * 生成追踪码
     * @return string
     */
    private function generateTrace()
    {
        return sprintf(
            '%s-%s-%d',
            bin2hex(random_bytes(8)),
            bin2hex(random_bytes(8)),
            time() * 1000
        );
    }
    
    /**
     * 发送HTTP GET请求
     * @param string $url
     * @return string|false
     */
    private function httpGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: GoldKlineService/1.0'
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            Log::error('HTTP请求失败：' . $error);
            return false;
        }
        
        return $response;
    }
    
    /**
     * 批量同步历史数据（循环获取）
     * @param int $klineType K线类型
     * @param int $totalNum 总共需要获取的数量
     * @return array
     */
    public function batchSyncHistory($klineType = 8, $totalNum = 5000)
    {
        $batchSize = 500; // 每次最多500条
        $totalSuccess = 0;
        $totalFail = 0;
        $iterations = ceil($totalNum / $batchSize);
        
        // 获取K线周期
        $period = GoldKline::getPeriodByType($klineType);
        
        // 检查数据库中是否已有数据，获取最早的时间戳
        $earliestKline = GoldKline::where([
            'period' => $period,
            'price_type' => $this->priceType
        ])->order('start_time', 'asc')->find();
        
        if ($earliestKline) {
            // 如果已有数据，从最早的数据继续往前获取
            $endTimestamp = $earliestKline->start_time;
            Log::info("数据库中已有数据，最早时间：" . $earliestKline->start_datetime . "（时间戳：{$endTimestamp}）");
            Log::info("从该时间点继续往前同步{$totalNum}条数据");
        } else {
            // 如果没有数据，从当前时间开始
            $endTimestamp = 0;
            Log::info("数据库中无数据，从当前时间开始同步{$totalNum}条数据");
        }
        
        Log::info("开始批量同步：目标{$totalNum}条，分{$iterations}次请求");
        
        for ($i = 0; $i < $iterations; $i++) {
            $queryNum = min($batchSize, $totalNum - $totalSuccess);
            
            Log::info("第" . ($i + 1) . "次请求：endTimestamp={$endTimestamp}, queryNum={$queryNum}");
            
            $result = $this->syncHistoryKline($klineType, $queryNum, $endTimestamp);
            
            if (!$result['success']) {
                Log::error("第" . ($i + 1) . "次请求失败：" . $result['message']);
                break;
            }
            
            $successCount = $result['data']['success_count'] ?? 0;
            $totalSuccess += $successCount;
            $totalFail += $result['data']['fail_count'] ?? 0;
            
            Log::info("第" . ($i + 1) . "次请求成功：{$successCount}条");
            
            // 如果获取的数据少于请求数量，说明已经没有更多数据了
            if ($successCount < $queryNum) {
                Log::info("获取数据少于请求数量，已到历史数据尽头");
                break;
            }
            
            // 获取最早一条数据的时间戳，用于下次请求（往更早的时间查）
            $klineList = $result['data']['kline_list'] ?? [];
            if (!empty($klineList)) {
                // 找出时间戳最小的（最早的）数据
                $timestamps = array_column($klineList, 'timestamp');
                $minTimestamp = min($timestamps);
                $endTimestamp = intval($minTimestamp);
                Log::info("下次请求从时间戳 {$endTimestamp} (" . date('Y-m-d H:i:s', $endTimestamp) . ") 开始");
            } else {
                Log::error("返回数据中没有kline_list");
                break;
            }
            
            // 避免请求过快
            sleep(2);
        }
        
        return [
            'success' => true,
            'message' => "批量同步完成，成功{$totalSuccess}条，失败{$totalFail}条",
            'data' => [
                'success_count' => $totalSuccess,
                'fail_count' => $totalFail
            ]
        ];
    }
}


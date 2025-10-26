<?php

namespace app\admin\controller;

use app\common\controller\BaseController;
use app\common\service\GoldKlineService;
use app\model\GoldKline as GoldKlineModel;
use app\model\GoldSyncLog;
use app\model\GoldApiConfig;
use think\facade\View;
use think\facade\Request;

/**
 * 黄金K线管理控制器
 */
class GoldKlineController extends BaseController
{
    /**
     * K线数据列表
     */
    public function index()
    {
        if (Request::isAjax()) {
            $page = input('page', 1);
            $limit = input('limit', 20);
            $period = input('period', '');
            $priceType = input('price_type', '');
            
            $where = [];
            if ($period) {
                $where[] = ['period', '=', $period];
            }
            if ($priceType) {
                $where[] = ['price_type', '=', $priceType];
            }
            
            $list = GoldKlineModel::where($where)
                ->order('start_time', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $page,
                ]);
            
            return json([
                'code' => 0,
                'msg' => 'success',
                'count' => $list->total(),
                'data' => $list->items()
            ]);
        }
        
        return View::fetch();
    }
    
    /**
     * K线图表展示
     */
    public function chart()
    {
        $period = input('period', '1day');
        $priceType = input('price_type', 'CNY');
        $limit = input('limit', 100);
        
        // 获取K线数据（先按时间倒序获取最新的N条）
        $klineData = GoldKlineModel::where([
            'period' => $period,
            'price_type' => $priceType
        ])
            ->order('start_time', 'desc')  // 倒序获取最新数据
            ->limit($limit)
            ->select()
            ->toArray();
        
        // 反转数组，使其按时间正序排列
        $klineData = array_reverse($klineData);
        
        // 转换为图表所需格式
        $chartData = [
            'timestamps' => [],
            'data' => []
        ];
        
        foreach ($klineData as $kline) {
            $chartData['timestamps'][] = $kline['start_datetime'];
            $chartData['data'][] = [
                floatval($kline['open_price']),
                floatval($kline['close_price']),
                floatval($kline['low_price']),
                floatval($kline['high_price']),
            ];
        }
        
        if (Request::isAjax()) {
            return json([
                'code' => 0,
                'msg' => 'success',
                'data' => $chartData
            ]);
        }
        
        View::assign('chartData', json_encode($chartData));
        return View::fetch();
    }
    
    /**
     * 同步日志列表
     */
    public function syncLog()
    {
        if (Request::isAjax()) {
            $page = input('page', 1);
            $limit = input('limit', 20);
            $taskType = input('task_type', '');
            $status = input('status', '');
            
            $where = [];
            if ($taskType) {
                $where[] = ['task_type', '=', $taskType];
            }
            if ($status) {
                $where[] = ['status', '=', $status];
            }
            
            $list = GoldSyncLog::where($where)
                ->order('id', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $page,
                ]);
            
            return json([
                'code' => 0,
                'msg' => 'success',
                'count' => $list->total(),
                'data' => $list->items()
            ]);
        }
        
        return View::fetch();
    }
    
    /**
     * API配置管理
     */
    /**
     * 配置列表
     */
    public function config()
    {
        $req = input();
        $where = [];
        
        // 搜索条件
        if (!empty($req['key'])) {
            $where[] = ['key', 'like', '%' . $req['key'] . '%'];
        }
        
        // 获取所有配置
        $data = GoldApiConfig::where($where)
            ->order('id', 'asc')
            ->select()
            ->toArray();
        
        View::assign([
            'data' => $data,
            'req' => $req
        ]);
        
        return View::fetch();
    }
    
    /**
     * 编辑配置
     */
    public function editConfig()
    {
        $id = input('id', 0);
        
        if (!$id) {
            return $this->error('参数错误');
        }
        
        $info = GoldApiConfig::find($id);
        
        if (!$info) {
            return $this->error('配置不存在');
        }
        
        View::assign([
            'info' => $info
        ]);
        
        return View::fetch();
    }
    
    /**
     * 保存配置
     */
    public function saveConfig()
    {
        if (!Request::isPost()) {
            return json(['code' => 0, 'msg' => '非法请求']);
        }
        
        $id = input('id', 0);
        $val = input('val', '');
        
        if (!$id) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        try {
            $config = GoldApiConfig::find($id);
            
            if (!$config) {
                return json(['code' => 0, 'msg' => '配置不存在']);
            }
            
            // 特殊处理：API Token 和 API地址，如果为空则不更新
            if (in_array($config['key'], ['api_token', 'api_url']) && empty($val)) {
                return json(['code' => 200, 'msg' => '配置未更改']);
            }
            
            $config->val = $val;
            $config->save();
            
            return json(['code' => 200, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '保存失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 旧的配置方法（兼容保留）
     */
    public function configOld()
    {
        // 获取当前配置，并提供默认值
        $config = GoldApiConfig::getAllConfig();
        
        // 确保所有配置项都有默认值
        $defaultConfig = [
            GoldApiConfig::KEY_API_TOKEN => '',
            GoldApiConfig::KEY_API_URL => 'https://quote.alltick.co/quote-b-api/kline',
            GoldApiConfig::KEY_GOLD_CODE => 'XAUCNH',
            GoldApiConfig::KEY_SYNC_INTERVAL => '60',
            GoldApiConfig::KEY_KLINE_TYPES => '8',
            GoldApiConfig::KEY_PRICE_TYPE => 'CNY',
            GoldApiConfig::KEY_IS_ENABLED => '1',
        ];
        
        // 合并配置，优先使用数据库中的配置
        $config = array_merge($defaultConfig, $config);
        
        View::assign('config', $config);
        
        return View::fetch();
    }
    
    /**
     * 手动触发同步
     */
    public function sync()
    {
        $type = input('type', 'realtime'); // history, realtime, batch
        $klineType = input('kline_type', 8);
        $queryNum = input('query_num', 500);
        $totalNum = input('total_num', 5000);
        
        $service = new GoldKlineService();
        
        try {
            switch ($type) {
                case 'history':
                    $result = $service->syncHistoryKline($klineType, $queryNum, 0);
                    break;
                    
                case 'batch':
                    $result = $service->batchSyncHistory($klineType, $totalNum);
                    break;
                    
                case 'realtime':
                default:
                    $klineTypesConfig = GoldApiConfig::getConfig(GoldApiConfig::KEY_KLINE_TYPES, '8');
                    $klineTypes = array_map('intval', explode(',', $klineTypesConfig));
                    $result = $service->syncLatestKline($klineTypes);
                    break;
            }
            
            if ($result['success']) {
                return json([
                    'code' => 1,
                    'msg' => $result['message'],
                    'data' => $result['data']
                ]);
            } else {
                return json([
                    'code' => 0,
                    'msg' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'msg' => '同步失败：' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 统计数据
     */
    public function statistics()
    {
        $stats = [];
        
        // K线数据统计
        $stats['kline_count'] = GoldKlineModel::count();
        $stats['kline_by_period'] = GoldKlineModel::field('period, count(*) as count')
            ->group('period')
            ->select()
            ->toArray();
        
        // 同步日志统计
        $stats['sync_total'] = GoldSyncLog::count();
        $stats['sync_success'] = GoldSyncLog::where('status', GoldSyncLog::STATUS_SUCCESS)->count();
        $stats['sync_failed'] = GoldSyncLog::where('status', GoldSyncLog::STATUS_FAILED)->count();
        $stats['sync_running'] = GoldSyncLog::where('status', GoldSyncLog::STATUS_RUNNING)->count();
        
        // 最新同步时间
        $latestSync = GoldSyncLog::order('id', 'desc')->find();
        $stats['latest_sync_time'] = $latestSync ? $latestSync->start_time : null;
        
        // 最新K线数据
        $latestKline = GoldKlineModel::order('start_time', 'desc')->find();
        $stats['latest_kline_time'] = $latestKline ? $latestKline->start_datetime : null;
        $stats['latest_price'] = $latestKline ? $latestKline->close_price : 0;
        
        return json([
            'code' => 1,
            'msg' => 'success',
            'data' => $stats
        ]);
    }
    
    /**
     * 删除K线数据
     */
    public function delete()
    {
        $id = input('id', 0);
        
        if (!$id) {
            return json([
                'code' => 0,
                'msg' => '参数错误'
            ]);
        }
        
        try {
            $kline = GoldKlineModel::find($id);
            if (!$kline) {
                return json([
                    'code' => 0,
                    'msg' => '数据不存在'
                ]);
            }
            
            $kline->delete();
            
            return json([
                'code' => 1,
                'msg' => '删除成功'
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'msg' => '删除失败：' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 批量删除K线数据
     */
    public function batchDelete()
    {
        $ids = input('ids', '');
        
        if (!$ids) {
            return json([
                'code' => 0,
                'msg' => '参数错误'
            ]);
        }
        
        try {
            $idArr = explode(',', $ids);
            GoldKlineModel::destroy($idArr);
            
            return json([
                'code' => 1,
                'msg' => '删除成功'
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'msg' => '删除失败：' . $e->getMessage()
            ]);
        }
    }
}


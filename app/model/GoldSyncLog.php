<?php

namespace app\model;

use think\Model;

/**
 * 黄金数据同步日志模型
 */
class GoldSyncLog extends Model
{
    protected $name = 'gold_sync_log';
    
    // 设置字段信息
    protected $schema = [
        'id'            => 'bigint',
        'task_type'     => 'string',
        'status'        => 'string',
        'start_time'    => 'datetime',
        'end_time'      => 'datetime',
        'duration'      => 'int',
        'data_count'    => 'int',
        'success_count' => 'int',
        'fail_count'    => 'int',
        'error_msg'     => 'text',
        'api_provider'  => 'string',
        'params'        => 'text',
        'created_at'    => 'datetime',
    ];
    
    // 自动时间戳
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;
    
    /**
     * 任务类型常量
     */
    const TASK_HISTORY_IMPORT  = 'history_import';  // 历史导入
    const TASK_REALTIME_FETCH  = 'realtime_fetch';  // 实时获取
    const TASK_KLINE_GENERATE  = 'kline_generate';  // K线生成
    
    /**
     * 状态常量
     */
    const STATUS_RUNNING = 'running'; // 执行中
    const STATUS_SUCCESS = 'success'; // 成功
    const STATUS_FAILED  = 'failed';  // 失败
    
    /**
     * 创建日志记录
     * @param string $taskType 任务类型
     * @param array $params 参数
     * @return GoldSyncLog
     */
    public static function createLog($taskType, $params = [])
    {
        $log = self::create([
            'task_type'    => $taskType,
            'status'       => self::STATUS_RUNNING,
            'start_time'   => date('Y-m-d H:i:s'),
            'params'       => json_encode($params, JSON_UNESCAPED_UNICODE),
            'api_provider' => $params['api_provider'] ?? 'alltick',
        ]);
        
        return $log;
    }
    
    /**
     * 更新日志状态为成功
     * @param int $dataCount 数据条数
     * @param int $successCount 成功条数
     * @param int $failCount 失败条数
     */
    public function markSuccess($dataCount = 0, $successCount = 0, $failCount = 0)
    {
        $endTime = date('Y-m-d H:i:s');
        $duration = strtotime($endTime) - strtotime($this->start_time);
        
        $this->save([
            'status'        => self::STATUS_SUCCESS,
            'end_time'      => $endTime,
            'duration'      => $duration,
            'data_count'    => $dataCount,
            'success_count' => $successCount,
            'fail_count'    => $failCount,
        ]);
    }
    
    /**
     * 更新日志状态为失败
     * @param string $errorMsg 错误信息
     */
    public function markFailed($errorMsg)
    {
        $endTime = date('Y-m-d H:i:s');
        $duration = strtotime($endTime) - strtotime($this->start_time);
        
        $this->save([
            'status'    => self::STATUS_FAILED,
            'end_time'  => $endTime,
            'duration'  => $duration,
            'error_msg' => $errorMsg,
        ]);
    }
}


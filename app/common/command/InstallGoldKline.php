<?php

namespace app\common\command;

use app\model\GoldApiConfig;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use think\facade\Db;

/**
 * 黄金K线系统安装命令
 * 
 * 使用方法：
 * php think install:gold-kline                    - 安装并初始化配置
 * php think install:gold-kline --token=your_token - 安装并设置API Token
 */
class InstallGoldKline extends Command
{
    protected function configure()
    {
        $this->setName('install:gold-kline')
            ->setDescription('安装黄金K线系统（创建表和初始化配置）')
            ->addOption('token', 't', Option::VALUE_OPTIONAL, 'API Token', '')
            ->addOption('code', 'c', Option::VALUE_OPTIONAL, '黄金产品代码', 'XAUCNH')
            ->addOption('force', 'f', Option::VALUE_NONE, '强制重新安装（会删除已有数据）')
            ->setHelp('该命令用于安装黄金K线系统，创建数据表并初始化配置');
    }
    
    protected function execute(Input $input, Output $output)
    {
        $output->writeln('');
        $output->writeln('<info>================================</info>');
        $output->writeln('<info>  黄金K线数据同步系统 - 安装  </info>');
        $output->writeln('<info>================================</info>');
        $output->writeln('');
        
        $token = $input->getOption('token');
        $goldCode = $input->getOption('code');
        $force = $input->getOption('force');
        
        try {
            // 步骤1: 检查数据表是否已存在
            $output->write('📋 步骤 1/3: 检查数据表... ');
            
            $tableExists = $this->checkTablesExist();
            
            if ($tableExists && !$force) {
                $output->writeln('<error>已存在</error>');
                $output->writeln('<comment>数据表已存在！如需重新安装，请使用 --force 参数</comment>');
                return;
            }
            
            $output->writeln('<info>准备创建</info>');
            
            // 步骤2: 创建数据表
            $output->write('📋 步骤 2/3: 创建数据表... ');
            
            if ($force && $tableExists) {
                $this->dropTables($output);
            }
            
            $this->createTables($output);
            $output->writeln('<info>✅ 成功</info>');
            
            // 步骤3: 初始化配置
            $output->write('📋 步骤 3/3: 初始化配置... ');
            $this->initConfig($token, $goldCode, $output);
            $output->writeln('<info>✅ 成功</info>');
            
            // 安装完成提示
            $output->writeln('');
            $output->writeln('<info>================================</info>');
            $output->writeln('<info>🎉 安装完成！</info>');
            $output->writeln('<info>================================</info>');
            $output->writeln('');
            
            $this->showNextSteps($output, $token);
            
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>❌ 安装失败：' . $e->getMessage() . '</error>');
            $output->writeln('<comment>错误详情：' . $e->getTraceAsString() . '</comment>');
        }
    }
    
    /**
     * 检查数据表是否存在
     */
    private function checkTablesExist()
    {
        try {
            $tables = [
                'mp_gold_price',
                'mp_gold_kline',
                'mp_gold_sync_log',
                'mp_gold_api_config'
            ];
            
            foreach ($tables as $table) {
                $exists = Db::query("SHOW TABLES LIKE '{$table}'");
                if (!empty($exists)) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 删除已有数据表
     */
    private function dropTables($output)
    {
        $output->writeln('');
        $output->writeln('<comment>  正在删除已有数据表...</comment>');
        
        $tables = [
            'mp_gold_api_config',
            'mp_gold_sync_log',
            'mp_gold_kline',
            'mp_gold_price'
        ];
        
        foreach ($tables as $table) {
            try {
                Db::execute("DROP TABLE IF EXISTS `{$table}`");
                $output->writeln("  - {$table} <info>✓</info>");
            } catch (\Exception $e) {
                $output->writeln("  - {$table} <error>✗</error>");
            }
        }
    }
    
    /**
     * 创建数据表
     */
    private function createTables($output)
    {
        // 1. 创建价格表
        $sql = "CREATE TABLE IF NOT EXISTS `mp_gold_price` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
            `price` decimal(10,2) NOT NULL COMMENT '黄金价格（单位：元/克 或 美元/盎司）',
            `price_type` varchar(20) NOT NULL DEFAULT 'CNY' COMMENT '价格类型：CNY-人民币/克，USD-美元/盎司',
            `timestamp` int(11) NOT NULL COMMENT '价格时间戳（秒）',
            `date_time` datetime NOT NULL COMMENT '价格日期时间（便于查询）',
            `source` varchar(50) NOT NULL DEFAULT 'api' COMMENT '数据来源：history-历史导入, realtime-实时获取, manual-手动录入',
            `api_provider` varchar(50) DEFAULT NULL COMMENT 'API供应商：alpha_vantage, yahoo_finance等',
            `raw_data` text COMMENT 'API返回的原始JSON数据（备份）',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_timestamp_type` (`timestamp`, `price_type`) COMMENT '同一时间同一类型只能有一条记录',
            KEY `idx_datetime` (`date_time`) COMMENT '日期时间索引',
            KEY `idx_source` (`source`) COMMENT '数据来源索引',
            KEY `idx_created` (`created_at`) COMMENT '创建时间索引'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黄金价格原始数据表'";
        Db::execute($sql);
        
        // 2. 创建K线表
        $sql = "CREATE TABLE IF NOT EXISTS `mp_gold_kline` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
            `period` varchar(20) NOT NULL COMMENT 'K线周期：1min, 5min, 15min, 30min, 1hour, 4hour, 1day, 1week, 1month',
            `price_type` varchar(20) NOT NULL DEFAULT 'CNY' COMMENT '价格类型：CNY-人民币/克，USD-美元/盎司',
            `open_price` decimal(10,2) NOT NULL COMMENT '开盘价',
            `high_price` decimal(10,2) NOT NULL COMMENT '最高价',
            `low_price` decimal(10,2) NOT NULL COMMENT '最低价',
            `close_price` decimal(10,2) NOT NULL COMMENT '收盘价',
            `volume` bigint(20) DEFAULT 0 COMMENT '成交量（如果API提供）',
            `amount` decimal(20,2) DEFAULT 0.00 COMMENT '成交额（如果API提供）',
            `start_time` int(11) NOT NULL COMMENT 'K线开始时间戳（秒）',
            `end_time` int(11) NOT NULL COMMENT 'K线结束时间戳（秒）',
            `start_datetime` datetime NOT NULL COMMENT 'K线开始日期时间',
            `end_datetime` datetime NOT NULL COMMENT 'K线结束日期时间',
            `data_count` int(11) DEFAULT 0 COMMENT '该K线包含的原始数据点数量',
            `is_completed` tinyint(1) DEFAULT 0 COMMENT '是否已完成：0-进行中, 1-已完成（周期已结束）',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_period_start_type` (`period`, `start_time`, `price_type`) COMMENT '同一周期同一开始时间同一类型唯一',
            KEY `idx_period_datetime` (`period`, `start_datetime`) COMMENT '周期+日期时间复合索引（常用查询）',
            KEY `idx_start_time` (`start_time`) COMMENT '开始时间索引',
            KEY `idx_completed` (`is_completed`) COMMENT '完成状态索引',
            KEY `idx_price_type` (`price_type`) COMMENT '价格类型索引'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黄金K线数据表'";
        Db::execute($sql);
        
        // 3. 创建同步日志表
        $sql = "CREATE TABLE IF NOT EXISTS `mp_gold_sync_log` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
            `task_type` varchar(50) NOT NULL COMMENT '任务类型：history_import-历史导入, realtime_fetch-实时获取, kline_generate-K线生成',
            `status` varchar(20) NOT NULL COMMENT '状态：running-执行中, success-成功, failed-失败',
            `start_time` datetime NOT NULL COMMENT '开始时间',
            `end_time` datetime DEFAULT NULL COMMENT '结束时间',
            `duration` int(11) DEFAULT 0 COMMENT '执行时长（秒）',
            `data_count` int(11) DEFAULT 0 COMMENT '处理数据条数',
            `success_count` int(11) DEFAULT 0 COMMENT '成功条数',
            `fail_count` int(11) DEFAULT 0 COMMENT '失败条数',
            `error_msg` text COMMENT '错误信息',
            `api_provider` varchar(50) DEFAULT NULL COMMENT 'API供应商',
            `params` text COMMENT '执行参数（JSON格式）',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            PRIMARY KEY (`id`),
            KEY `idx_task_type` (`task_type`) COMMENT '任务类型索引',
            KEY `idx_status` (`status`) COMMENT '状态索引',
            KEY `idx_start_time` (`start_time`) COMMENT '开始时间索引'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黄金数据同步日志表'";
        Db::execute($sql);
        
        // 4. 创建API配置表
        $sql = "CREATE TABLE IF NOT EXISTS `mp_gold_api_config` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
            `description` varchar(50) NOT NULL COMMENT '描述',
            `key` varchar(255) DEFAULT NULL COMMENT '配置键',
            `val` varchar(500) NOT NULL COMMENT '配置值',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_key` (`key`) COMMENT '配置键唯一'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黄金API配置表'";
        Db::execute($sql);
    }
    
    /**
     * 初始化配置
     */
    private function initConfig($token, $goldCode, $output)
    {
        $configs = [
            [
                'description' => 'API Token',
                'key' => GoldApiConfig::KEY_API_TOKEN,
                'val' => $token ?: ''
            ],
            [
                'description' => 'API地址',
                'key' => GoldApiConfig::KEY_API_URL,
                'val' => 'https://quote.alltick.co/quote-b-api/kline'
            ],
            [
                'description' => '黄金产品代码',
                'key' => GoldApiConfig::KEY_GOLD_CODE,
                'val' => $goldCode ?: 'XAUCNH'
            ],
            [
                'description' => '同步间隔（秒）',
                'key' => GoldApiConfig::KEY_SYNC_INTERVAL,
                'val' => '60'
            ],
            [
                'description' => 'K线类型（逗号分隔）',
                'key' => GoldApiConfig::KEY_KLINE_TYPES,
                'val' => '8'
            ],
            [
                'description' => '价格类型',
                'key' => GoldApiConfig::KEY_PRICE_TYPE,
                'val' => 'CNY'  // 默认人民币
            ],
            [
                'description' => '是否启用',
                'key' => GoldApiConfig::KEY_IS_ENABLED,
                'val' => '1'
            ]
        ];
        
        foreach ($configs as $config) {
            GoldApiConfig::create($config);
        }
    }
    
    /**
     * 显示后续步骤提示
     */
    private function showNextSteps($output, $token)
    {
        $output->writeln('<comment>📝 接下来的步骤：</comment>');
        $output->writeln('');
        
        if (empty($token)) {
            $output->writeln('1. <comment>配置API Token（重要！）</comment>');
            $output->writeln('   方式一：访问后台管理');
            $output->writeln('   路径：/admin/GoldKline/config');
            $output->writeln('');
            $output->writeln('   方式二：使用命令行');
            $output->writeln('   php think config:gold-kline --token=你的API_TOKEN');
            $output->writeln('');
            $output->writeln('   <info>获取Token: https://alltick.co</info>');
            $output->writeln('');
        }
        
        $output->writeln('2. <comment>测试同步功能</comment>');
        $output->writeln('   php think sync:gold-kline --type=realtime');
        $output->writeln('');
        
        $output->writeln('3. <comment>配置定时任务（推荐）</comment>');
        $output->writeln('   crontab -e');
        $output->writeln('   添加：* * * * * cd ' . getcwd() . ' && php think sync:gold-kline >> /tmp/gold_kline.log 2>&1');
        $output->writeln('');
        
        $output->writeln('4. <comment>访问后台管理</comment>');
        $output->writeln('   K线数据：/admin/GoldKline/index');
        $output->writeln('   K线图表：/admin/GoldKline/chart');
        $output->writeln('   同步日志：/admin/GoldKline/syncLog');
        $output->writeln('');
        
        $output->writeln('<info>📖 详细文档：</info>');
        $output->writeln('   - GOLD_KLINE_README.md (快速入门)');
        $output->writeln('   - gold_kline_usage.md (详细文档)');
        $output->writeln('');
        $output->writeln('<info>================================</info>');
    }
}


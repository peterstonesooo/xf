<?php

namespace app\common\command;

use app\common\service\GoldKlineService;
use app\model\GoldApiConfig;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;

/**
 * 同步黄金K线数据命令
 * 
 * 使用方法：
 * php think sync:gold-kline                         - 同步最新K线（默认日K）
 * php think sync:gold-kline --type=history          - 同步历史K线
 * php think sync:gold-kline --type=realtime         - 同步最新K线
 * php think sync:gold-kline --type=batch            - 批量同步历史数据
 * php think sync:gold-kline --kline=8 --num=1000    - 同步指定类型和数量的历史K线
 * php think sync:gold-kline -t history -k 5         - 使用简写参数
 */
class SyncGoldKline extends Command
{
    protected function configure()
    {
        $this->setName('sync:gold-kline')
            ->setDescription('同步黄金K线数据')
            ->addOption('type', 't', Option::VALUE_OPTIONAL, '同步类型：history-历史数据, realtime-实时数据, batch-批量历史', 'realtime')
            ->addOption('kline', 'k', Option::VALUE_OPTIONAL, 'K线类型（1-10）', '8')
            ->addOption('num', null, Option::VALUE_OPTIONAL, '查询数量', '500')
            ->addOption('total', null, Option::VALUE_OPTIONAL, '批量同步总数量', '5000')
            ->setHelp('该命令用于同步黄金K线数据，支持历史数据和实时数据同步');
    }
    
    protected function execute(Input $input, Output $output)
    {
        // 检查是否启用
        $isEnabled = GoldApiConfig::getConfig(GoldApiConfig::KEY_IS_ENABLED, '1');
        if ($isEnabled != '1') {
            $output->writeln('<error>黄金K线同步功能未启用</error>');
            return;
        }
        
        $service = new GoldKlineService();
        
        $type = $input->getOption('type');
        $klineType = intval($input->getOption('kline'));
        $queryNum = intval($input->getOption('num'));
        $totalNum = intval($input->getOption('total'));
        
        $output->writeln('<info>开始同步黄金K线数据...</info>');
        $output->writeln("同步类型: {$type}");
        
        switch ($type) {
            case 'history':
                // 同步历史数据
                $output->writeln("K线类型: {$klineType}, 查询数量: {$queryNum}");
                $result = $service->syncHistoryKline($klineType, $queryNum, 0);
                break;
                
            case 'batch':
                // 批量同步历史数据
                $output->writeln("K线类型: {$klineType}, 总数量: {$totalNum}");
                $result = $service->batchSyncHistory($klineType, $totalNum);
                break;
                
            case 'realtime':
            default:
                // 同步最新数据（支持多个K线类型，从配置读取）
                $klineTypesConfig = GoldApiConfig::getConfig(GoldApiConfig::KEY_KLINE_TYPES, '8');
                $klineTypes = array_map('intval', explode(',', $klineTypesConfig));
                $output->writeln("K线类型: " . implode(',', $klineTypes));
                $result = $service->syncLatestKline($klineTypes);
                break;
        }
        
        if ($result['success']) {
            $output->writeln('<info>' . $result['message'] . '</info>');
        } else {
            $output->writeln('<error>' . $result['message'] . '</error>');
        }
        
        $output->writeln('<info>同步完成！</info>');
    }
}


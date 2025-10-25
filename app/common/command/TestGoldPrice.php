<?php
declare(strict_types=1);

namespace app\common\command;

use app\common\service\GoldKlineService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 测试黄金实时价格命令
 * 
 * 使用方法：
 * php think test:gold-price              - 测试获取实时金价（使用缓存）
 * php think test:gold-price --refresh    - 强制刷新获取实时金价
 * php think test:gold-price --loop=10    - 循环测试10次（观察缓存效果）
 */
class TestGoldPrice extends Command
{
    protected function configure()
    {
        $this->setName('test:gold-price')
            ->setDescription('测试黄金实时价格获取功能')
            ->addOption('refresh', 'r', \think\console\input\Option::VALUE_NONE, '强制刷新（跳过缓存）')
            ->addOption('loop', 'l', \think\console\input\Option::VALUE_OPTIONAL, '循环测试次数', '1')
            ->addOption('interval', 'i', \think\console\input\Option::VALUE_OPTIONAL, '循环间隔（秒）', '1')
            ->setHelp('该命令用于测试黄金实时价格获取和Redis缓存功能');
    }
    
    protected function execute(Input $input, Output $output)
    {
        $refresh = $input->getOption('refresh');
        $loopCount = intval($input->getOption('loop'));
        $interval = intval($input->getOption('interval'));
        
        $output->writeln('<info>========== 黄金实时价格测试 ==========</info>');
        $output->writeln('测试模式: ' . ($refresh ? '强制刷新' : '自动缓存'));
        $output->writeln('循环次数: ' . $loopCount);
        
        if ($loopCount > 1) {
            $output->writeln('循环间隔: ' . $interval . ' 秒');
        }
        
        $output->writeln('');
        
        $service = new GoldKlineService();
        
        for ($i = 1; $i <= $loopCount; $i++) {
            if ($loopCount > 1) {
                $output->writeln("<comment>---------- 第 {$i} 次测试 ----------</comment>");
            }
            
            $startTime = microtime(true);
            
            // 获取最新金价
            $result = $service->getLatestPrice($refresh);
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            if ($result['success']) {
                $output->writeln('<info>✓ 获取成功</info>');
                $output->writeln('当前金价: ' . $result['price'] . ' 元/克');
                $output->writeln('数据来源: ' . ($result['from_cache'] ? 'Redis缓存' : 'API接口'));
                $output->writeln('响应时间: ' . $duration . ' ms');
                
                if (isset($result['data'])) {
                    $data = $result['data'];
                    $output->writeln('');
                    $output->writeln('详细信息:');
                    $output->writeln('  产品代码: ' . ($data['code'] ?? '-'));
                    $output->writeln('  成交价格: ' . ($data['price'] ?? '-') . ' 元/克');
                    $output->writeln('  原始价格: ' . ($data['original_price'] ?? '-') . ' 美元/盎司');
                    $output->writeln('  成交量: ' . ($data['volume'] ?? '-'));
                    $output->writeln('  成交额: ' . ($data['turnover'] ?? '-'));
                    $output->writeln('  成交时间: ' . ($data['tick_datetime'] ?? '-'));
                    $output->writeln('  交易方向: ' . $this->getTradeDirectionText($data['trade_direction'] ?? 0));
                    $output->writeln('  价格类型: ' . ($data['price_type'] ?? '-'));
                }
                
            } else {
                $output->writeln('<error>✗ 获取失败</error>');
                $output->writeln('错误信息: ' . $result['message']);
            }
            
            // 如果有多次循环且不是最后一次，则等待
            if ($i < $loopCount) {
                $output->writeln('');
                sleep($interval);
            }
        }
        
        $output->writeln('');
        $output->writeln('<info>========== 测试完成 ==========</info>');
        
        // 如果是循环测试，显示缓存效果说明
        if ($loopCount > 1 && !$refresh) {
            $output->writeln('');
            $output->writeln('<comment>提示：</comment>');
            $output->writeln('- 第1次请求通常从API获取（响应较慢）');
            $output->writeln('- 后续5秒内的请求从Redis缓存获取（响应极快）');
            $output->writeln('- 5秒后缓存过期，再次从API获取');
        }
    }
    
    /**
     * 获取交易方向文本
     * @param int $direction
     * @return string
     */
    private function getTradeDirectionText($direction)
    {
        $map = [
            0 => '中性盘',
            1 => '主动买入',
            2 => '主动卖出',
        ];
        
        return $map[$direction] ?? '未知';
    }
}


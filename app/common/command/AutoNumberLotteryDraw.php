<?php

namespace app\common\command;

use app\model\NumberLotteryDraw;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;
use Exception;

/**
 * 数字抽奖自动开奖任务
 * 
 * 功能：如果今天没有开奖，就随机生成一个号码并开奖
 * 
 * 使用方法：
 * php think auto:number-lottery-draw
 * 
 * 建议使用定时任务（crontab）每天19点后执行：
 * 0 19 * * * cd /path/to/project && php think auto:number-lottery-draw >> /path/to/runtime/log/auto-lottery-draw.log 2>&1
 */
class AutoNumberLotteryDraw extends Command
{
    protected function configure()
    {
        $this->setName('auto:number-lottery-draw')
            ->setDescription('数字抽奖自动开奖任务（如果今天没有开奖，就随机生成一个号码并开奖）');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('');
        $output->writeln('<info>================================</info>');
        $output->writeln('<info>  数字抽奖自动开奖任务</info>');
        $output->writeln('<info>================================</info>');
        $output->writeln('');

        try {
            $drawDate = date('Y-m-d');
            $output->writeln("检查日期：{$drawDate}");

            // 检查今天是否已经开过奖
            $todayDraw = NumberLotteryDraw::where('draw_date', $drawDate)
                ->where('status', 1) // 已开奖状态
                ->find();

            if ($todayDraw) {
                $output->writeln("<comment>今天已经设置过中奖号码了</comment>");
                $output->writeln("中奖号码：<info>{$todayDraw->winning_number}</info>");
                $output->writeln("开奖时间：{$todayDraw->draw_time}");
                
                // 检查是否已经更新过用户抽奖记录（通过ticket_status判断）
                // 如果还有待开奖状态的记录，说明还没更新
                $pendingCount = \app\model\NumberLotteryTicket::where('lottery_date', $drawDate)
                    ->where('status', 1)
                    ->where('ticket_status', 1) // 待开奖状态
                    ->count();
                
                if ($pendingCount == 0) {
                    // 统计中奖人数
                    $winCount = \app\model\NumberLotteryTicket::where('lottery_date', $drawDate)
                        ->where('draw_id', $todayDraw->id)
                        ->where('ticket_status', 3) // 已中奖状态
                        ->count();
                    
                    $output->writeln("中奖人数（已更新）：{$winCount}");
                    $output->writeln('');
                    $output->writeln('<info>任务完成：用户抽奖记录已更新</info>');
                    return;
                }
                
                $output->writeln("待更新记录数：{$pendingCount}");
                
                // 如果设置了中奖号码但还没更新用户抽奖记录，则更新
                $output->writeln("<comment>开始更新用户抽奖记录...</comment>");
                $result = $this->updateTicketsByDraw($todayDraw, $drawDate);
                
                $output->writeln('');
                $output->writeln('<info>✅ 更新成功！</info>');
                $output->writeln("中奖人数：<info>{$result['win_count']}</info>");
                $output->writeln('');
                $output->writeln('<info>任务完成</info>');
                return;
            }

            // 如果今天没有开奖，随机生成一个6位数字
            $output->writeln("<comment>今天还没有开奖，开始自动开奖...</comment>");
            
            $winningNumber = $this->generateRandomNumber();
            $output->writeln("随机生成的中奖号码：<info>{$winningNumber}</info>");

            // 调用模型方法开奖（更新用户抽奖记录）
            $result = NumberLotteryDraw::setWinningNumber(
                $winningNumber,
                null, // 不设置多等级中奖号码
                $drawDate,
                '系统自动开奖', // 备注
                0, // 操作员ID为0表示系统自动
                true // 自动开奖需要更新用户抽奖记录
            );

            $output->writeln('');
            $output->writeln('<info>✅ 开奖成功！</info>');
            $output->writeln("开奖记录ID：{$result['draw_id']}");
            $output->writeln("中奖号码：<info>{$result['winning_number']}</info>");
            $output->writeln("总抽奖次数：{$result['total_tickets']}");
            $output->writeln("参与用户数：{$result['total_users']}");
            $output->writeln("中奖人数：<info>{$result['win_count']}</info>");
            $output->writeln('');

            // 记录日志
            Log::info('数字抽奖自动开奖成功', [
                'draw_date' => $drawDate,
                'winning_number' => $winningNumber,
                'win_count' => $result['win_count'],
                'total_tickets' => $result['total_tickets'],
            ]);

            $output->writeln('<info>任务完成</info>');

        } catch (Exception $e) {
            $errorMsg = '自动开奖失败：' . $e->getMessage();
            $output->writeln('');
            $output->writeln("<error>❌ {$errorMsg}</error>");
            $output->writeln('');
            
            // 记录错误日志
            Log::error('数字抽奖自动开奖失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 生成随机6位数字
     * @return string
     */
    private function generateRandomNumber()
    {
        // 生成6位随机数字（000000-999999）
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * 根据开奖记录更新用户抽奖记录
     * @param \app\model\NumberLotteryDraw $drawRecord 开奖记录
     * @param string $drawDate 开奖日期
     * @return array 更新结果
     * @throws Exception
     */
    private function updateTicketsByDraw($drawRecord, $drawDate)
    {
        // 获取今日所有待开奖的抽奖记录（只处理ticket_status = 1的记录）
        $tickets = \app\model\NumberLotteryTicket::where('lottery_date', $drawDate)
            ->where('status', 1)
            ->where('ticket_status', 1) // 只获取待开奖状态的记录
            ->select();
        
        if (empty($tickets)) {
            return [
                'win_count' => 0,
                'total_tickets' => 0,
            ];
        }
        
        $winningNumber = $drawRecord->winning_number;
        $winningNumbersJson = $drawRecord->winning_numbers;
        
        // 查找中奖的抽奖记录
        $winTickets = NumberLotteryDraw::findWinningTickets($tickets, $winningNumber, $winningNumbersJson);
        $winCount = count($winTickets);
        
        // 更新用户抽奖记录（会自动更新ticket_status：2-未中奖，3-中奖）
        NumberLotteryDraw::updateTicketWinStatus($drawDate, $winTickets, $winningNumber, $winningNumbersJson, $drawRecord->id);
        
        return [
            'win_count' => $winCount,
            'total_tickets' => count($tickets),
        ];
    }
}


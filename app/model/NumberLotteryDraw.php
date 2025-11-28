<?php

namespace app\model;

use think\Model;
use think\facade\Db;
use Exception;

class NumberLotteryDraw extends Model
{
    protected $table = 'mp_number_lottery_draw';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 设置字段信息
    protected $schema = [
        'id'             => 'int',
        'draw_date'      => 'date',
        'winning_number' => 'string',
        'winning_numbers' => 'string',
        'draw_time'      => 'datetime',
        'status'         => 'int',
        'total_tickets'  => 'int',
        'total_users'    => 'int',
        'win_count'      => 'int',
        'remark'         => 'string',
        'operator_id'    => 'int',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    /**
     * 设置开奖号码并更新中奖记录
     * @param string $winningNumber 主中奖号码（6位数字）
     * @param string|null $winningNumbersJson 多等级中奖号码JSON字符串（可选）
     * @param string|null $drawDate 开奖日期，默认为今天
     * @param string|null $remark 备注
     * @param int|null $operatorId 操作员ID
     * @param bool $updateTickets 是否更新用户抽奖记录的中奖状态，默认true
     * @return array 返回开奖结果 ['draw_id' => int, 'winning_number' => string, 'win_count' => int, 'total_tickets' => int]
     * @throws Exception
     */
    public static function setWinningNumber($winningNumber, $winningNumbersJson = null, $drawDate = null, $remark = null, $operatorId = null, $updateTickets = true)
    {
        // 验证中奖号码
        if (empty($winningNumber) || !preg_match('/^\d{6}$/', $winningNumber)) {
            throw new Exception('中奖号码必须是6位数字');
        }

        // 验证多等级中奖号码JSON格式
        $winningNumbers = null;
        if (!empty($winningNumbersJson)) {
            $winningNumbers = json_decode($winningNumbersJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($winningNumbers)) {
                throw new Exception('多等级中奖号码JSON格式错误');
            }
            $winningNumbers = json_encode($winningNumbers, JSON_UNESCAPED_UNICODE);
        }

        // 设置默认值
        if ($drawDate === null) {
            $drawDate = date('Y-m-d');
        }
        $drawTime = date('Y-m-d H:i:s');

        Db::startTrans();
        try {
            // 检查指定日期是否已经开过奖
            $drawRecord = self::where('draw_date', $drawDate)->find();
            
            // 统计指定日期的抽奖数据
            $tickets = NumberLotteryTicket::where('lottery_date', $drawDate)
                ->where('status', 1)
                ->select();
            
            $totalTickets = count($tickets);
            $totalUsers = NumberLotteryTicket::where('lottery_date', $drawDate)
                ->where('status', 1)
                ->group('user_id')
                ->count();
            
            // 查找中奖的抽奖记录
            $winTickets = self::findWinningTickets($tickets, $winningNumber, $winningNumbers);
            $winCount = count($winTickets);
            
            // 创建或更新开奖记录
            if ($drawRecord) {
                // 更新开奖记录
                $drawRecord->winning_number = $winningNumber;
                $drawRecord->winning_numbers = $winningNumbers;
                $drawRecord->draw_time = $drawTime;
                $drawRecord->status = 1;
                $drawRecord->total_tickets = $totalTickets;
                $drawRecord->total_users = $totalUsers;
                $drawRecord->win_count = $winCount;
                $drawRecord->remark = $remark;
                $drawRecord->operator_id = $operatorId;
                $drawRecord->save();
                $drawId = $drawRecord->id;
            } else {
                // 创建开奖记录
                $drawRecord = self::create([
                    'draw_date' => $drawDate,
                    'winning_number' => $winningNumber,
                    'winning_numbers' => $winningNumbers,
                    'draw_time' => $drawTime,
                    'status' => 1,
                    'total_tickets' => $totalTickets,
                    'total_users' => $totalUsers,
                    'win_count' => $winCount,
                    'remark' => $remark,
                    'operator_id' => $operatorId,
                ]);
                $drawId = $drawRecord->id;
            }
            
            // 根据参数决定是否更新所有抽奖记录的中奖状态
            if ($updateTickets) {
                self::updateTicketWinStatus($drawDate, $winTickets, $winningNumber, $winningNumbers, $drawId);
            }
            
            Db::commit();
            
            return [
                'draw_id' => $drawId,
                'winning_number' => $winningNumber,
                'win_count' => $winCount,
                'total_tickets' => $totalTickets,
                'total_users' => $totalUsers,
            ];
            
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 查找中奖的抽奖记录
     * @param array $tickets 抽奖记录列表
     * @param string $winningNumber 主中奖号码
     * @param string|null $winningNumbersJson 多等级中奖号码JSON字符串
     * @return array 中奖的抽奖记录
     */
    public static function findWinningTickets($tickets, $winningNumber, $winningNumbersJson = null)
    {
        $winTickets = [];
        $winningNumbersArray = [];
        
        if ($winningNumbersJson) {
            $winningNumbersArray = json_decode($winningNumbersJson, true) ?: [];
        }
        
        foreach ($tickets as $ticket) {
            // 检查是否匹配主中奖号码
            if ($ticket['ticket_number'] == $winningNumber) {
                $winTickets[] = $ticket;
                continue;
            }
            
            // 检查是否匹配多等级中奖号码
            if (!empty($winningNumbersArray)) {
                foreach ($winningNumbersArray as $level => $number) {
                    if ($ticket['ticket_number'] == $number) {
                        $winTickets[] = $ticket;
                        break;
                    }
                }
            }
        }
        
        return $winTickets;
    }

    /**
     * 更新抽奖记录的中奖状态
     * @param string $drawDate 开奖日期
     * @param array $winTickets 中奖的抽奖记录
     * @param string $winningNumber 主中奖号码
     * @param string|null $winningNumbersJson 多等级中奖号码JSON字符串
     * @param int $drawId 开奖记录ID
     */
    public static function updateTicketWinStatus($drawDate, $winTickets, $winningNumber, $winningNumbersJson = null, $drawId)
    {
        // 先获取中奖记录的ID列表，用于后续排除
        $winTicketIds = [];
        if (!empty($winTickets)) {
            foreach ($winTickets as $ticket) {
                $winTicketIds[] = $ticket['id'];
            }
        }
        
        // 更新所有待开奖记录为非中奖（状态：2-已开奖未中奖）
        // 排除中奖记录，因为中奖记录需要单独更新
        $updateQuery = NumberLotteryTicket::where('lottery_date', $drawDate)
            ->where('status', 1)
            ->where('ticket_status', 1); // 只更新待开奖状态的记录
        
        if (!empty($winTicketIds)) {
            $updateQuery->whereNotIn('id', $winTicketIds); // 排除中奖记录
        }
        
        $updateQuery->update([
            'is_win' => 0,
            'win_level' => null,
            'win_prize' => null,
            'draw_id' => $drawId,
            'ticket_status' => 2, // 2-已开奖未中奖
        ]);
        
        // 更新中奖记录（状态：3-已中奖）
        if (!empty($winTickets)) {
            $winningNumbersArray = [];
            if ($winningNumbersJson) {
                $winningNumbersArray = json_decode($winningNumbersJson, true) ?: [];
            }
            
            foreach ($winTickets as $ticket) {
                $winLevel = null;
                $winPrize = null;
                
                // 判断中奖等级
                if ($ticket['ticket_number'] == $winningNumber) {
                    $winLevel = '特等奖';
                    $winPrize = '特等奖';
                } else {
                    // 查找在多等级中的位置
                    foreach ($winningNumbersArray as $level => $number) {
                        if ($ticket['ticket_number'] == $number) {
                            $winLevel = $level;
                            $winPrize = $level;
                            break;
                        }
                    }
                }
                
                // 更新中奖记录，允许 ticket_status 为 1 或 2（可能已经被第一步更新为2了）
                NumberLotteryTicket::where('id', $ticket['id'])
                    ->whereIn('ticket_status', [1, 2]) // 允许待开奖或已开奖未中奖状态
                    ->update([
                        'is_win' => 1,
                        'win_level' => $winLevel,
                        'win_prize' => $winPrize,
                        'draw_id' => $drawId,
                        'ticket_status' => 3, // 3-已中奖
                    ]);
            }
        }
    }
}


<?php

namespace app\model;

use think\Model;
use think\facade\Db;
use think\facade\Log;
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
        'money'          => 'int',
        'moneys'         => 'string',
        'log_type'       => 'int',
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
     * @param int|null $money 中奖金额（可选）
     * @param string|null $moneys 多奖金JSON字符串（可选）
     * @param int $logType 中奖钱包类型，默认13（普惠钱包）
     * @param int $status 开奖状态，0=未开奖，1=已开奖，默认1
     * @return array 返回开奖结果 ['draw_id' => int, 'winning_number' => string, 'win_count' => int, 'total_tickets' => int]
     * @throws Exception
     */
    public static function setWinningNumber($winningNumber, $winningNumbersJson = null, $drawDate = null, $remark = null, $operatorId = null, $updateTickets = true, $money = null, $moneys = null, $logType = 13, $status = 1)
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

        // 验证多奖金JSON格式
        $moneysJson = null;
        if (!empty($moneys)) {
            $moneysArray = json_decode($moneys, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($moneysArray)) {
                throw new Exception('多奖金JSON格式错误');
            }
            $moneysJson = json_encode($moneysArray, JSON_UNESCAPED_UNICODE);
        }

        // 处理金额（转换为整数）
        $moneyInt = $money !== null ? intval($money) : 0;

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
                $drawRecord->status = $status; // 使用传入的状态值
                $drawRecord->total_tickets = $totalTickets;
                $drawRecord->total_users = $totalUsers;
                $drawRecord->win_count = $winCount;
                $drawRecord->money = $moneyInt;
                $drawRecord->moneys = $moneysJson;
                $drawRecord->log_type = $logType;
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
                    'status' => $status, // 使用传入的状态值
                    'total_tickets' => $totalTickets,
                    'total_users' => $totalUsers,
                    'win_count' => $winCount,
                    'money' => $moneyInt,
                    'moneys' => $moneysJson,
                    'log_type' => $logType,
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
     * 更新抽奖记录的中奖状态并发放奖金
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
            
            // 发放奖金给中奖用户
            self::distributeRewardsToWinners($drawId, $winTickets);
        }
    }

    /**
     * 发放奖金给中奖用户
     * @param int $drawId 开奖记录ID
     * @param array $winTickets 中奖的抽奖记录
     */
    private static function distributeRewardsToWinners($drawId, $winTickets)
    {
        if (empty($winTickets)) {
            return;
        }

        // 获取开奖记录
        $drawRecord = self::where('id', $drawId)->find();
        if (!$drawRecord) {
            Log::error('数字抽奖发放奖金失败：开奖记录不存在', ['draw_id' => $drawId]);
            return;
        }

        // 获取奖金信息
        $money = $drawRecord->money ?? 0; // 单个奖金金额（分）
        $moneysJson = $drawRecord->moneys; // 多等级奖金JSON
        $logType = $drawRecord->log_type ?? 13; // 钱包类型，默认13（普惠钱包）

        // 解析多等级奖金
        $moneysArray = [];
        if (!empty($moneysJson)) {
            $moneysArray = json_decode($moneysJson, true) ?: [];
        }

        // 确定钱包字段（log_type = 13 对应普惠钱包 puhui）
        $walletField = 'puhui'; // 默认普惠钱包
        if ($logType == 13) {
            $walletField = 'puhui';
        } elseif ($logType == 14) {
            $walletField = 'zhenxing_wallet';
        } elseif ($logType == 16) {
            $walletField = 'gongfu_wallet';
        }

        // 遍历中奖记录，发放奖金
        foreach ($winTickets as $ticket) {
            try {
                // 确定奖金金额
                $rewardAmount = 0;
                $winLevel = $ticket['win_level'] ?? null;

                if (!empty($moneysArray) && !empty($winLevel) && isset($moneysArray[$winLevel])) {
                    // 使用多等级奖金
                    $rewardAmount = intval($moneysArray[$winLevel]);
                } elseif ($money > 0) {
                    // 使用单个奖金
                    $rewardAmount = $money;
                }

                // 如果奖金为0，跳过
                if ($rewardAmount <= 0) {
                    continue;
                }

                // 发放奖金
                $drawDate = $drawRecord->draw_date;
                $ticketNumber = $ticket['ticket_number'];
                $remark = "数字抽奖中奖奖励（{$drawDate}，中奖号码：{$ticketNumber}";
                if ($winLevel) {
                    $remark .= "，等级：{$winLevel}";
                }
                $remark .= "）";

                \app\model\User::changeInc(
                    $ticket['user_id'],
                    $rewardAmount,
                    $walletField,
                    128, // type: 128 表示数字抽奖中奖奖励
                    0, // relation_id
                    $logType, // log_type
                    $remark,
                    0, // admin_user_id
                    2, // status: 2 表示已完成
                    'CJ', // sn_prefix: 抽奖
                    0 // is_delete
                );

            } catch (Exception $e) {
                Log::error('数字抽奖发放奖金失败', [
                    'user_id' => $ticket['user_id'] ?? 0,
                    'ticket_id' => $ticket['id'] ?? 0,
                    'draw_id' => $drawId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}


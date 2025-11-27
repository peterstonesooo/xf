<?php

namespace app\model;

use think\Model;

class NumberLotteryTicket extends Model
{
    protected $table = 'mp_number_lottery_ticket';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 设置字段信息
    protected $schema = [
        'id'            => 'int',
        'user_id'       => 'int',
        'ticket_number' => 'string',
        'lottery_date'  => 'date',
        'draw_id'       => 'int',
        'is_win'        => 'int',
        'win_level'     => 'string',
        'win_prize'     => 'string',
        'status'        => 'int',
        'ticket_status' => 'int',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // 抽奖状态映射
    public static $ticketStatusMap = [
        1 => '待开奖',
        2 => '已开奖未中奖',
        3 => '已中奖',
    ];

    /**
     * 获取抽奖状态文本
     */
    public function getTicketStatusTextAttr($value, $data)
    {
        return self::$ticketStatusMap[$data['ticket_status']] ?? '未知';
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 关联开奖记录
    public function draw()
    {
        return $this->belongsTo(NumberLotteryDraw::class, 'draw_id');
    }
}


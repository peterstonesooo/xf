<?php

namespace app\api\controller;

use app\model\NumberLotteryTicket;
use app\model\NumberLotteryDraw;
use app\model\User;
use app\model\OrderTongxing;
use think\facade\Cache;
use think\facade\Db;
use Exception;

class NumberLotteryController extends AuthController
{
    /**
     * 数字抽奖 - 生成抽奖号码
     * @return \think\response\Json
     */
    public function draw()
    {
        $user = $this->user;
        $userId = $user['id'];
        $lotteryDate = date('Y-m-d');
        $currentTime = time();
        $limitTime = strtotime(date('Y-m-d') . ' 19:30:00');
        
        // 检查是否在19:30之后（19:30及之后不能抽奖）
        if ($currentTime >= $limitTime) {
            return out(null, 10001, '每天19:30后不能抽奖，请明天再来');
        }

        // 检查用户是否捐款（只有捐款了的用户才能抽奖）
        $hasDonation = OrderTongxing::where('user_id', $userId)
            ->where('status', '>', 1)  // 已支付状态
            ->where('created_at','>','2025-12-13 00:00:00')
            ->where('pay_time', '>', 0)  // 有支付时间
            ->count();
        
        if ($hasDonation == 0) {
            return out(null, 10001, '未捐赠用户暂无法领取号码，敬请理解并支持公益事业！');
        }
        
        // 防重复提交（5秒内）
        $cacheKey = 'number_lottery_draw_' . $userId . '_' . $lotteryDate;
        if (Cache::get($cacheKey)) {
            return out(null, 10001, '请勿频繁抽奖，请稍后再试');
        }
        Cache::set($cacheKey, time(), 5);
        
        // 检查今天是否已经抽过奖
        $todayTicket = NumberLotteryTicket::where('user_id', $userId)
            ->where('lottery_date', $lotteryDate)
            ->where('status', 1)
            ->find();
        
        if ($todayTicket) {
            return out(null, 10001, '您今天已经抽过奖了，请明天再来');
        }
        
        Db::startTrans();
        try {
            // 生成抽奖号码（6位随机数字，当天唯一）
            $ticketNumber = $this->generateUniqueTicketNumber($lotteryDate);
            
            // 创建抽奖记录
            $ticket = NumberLotteryTicket::create([
                'user_id' => $userId,
                'ticket_number' => $ticketNumber,
                'lottery_date' => $lotteryDate,
                'is_win' => 0,
                'status' => 1,
                'ticket_status' => 1, // 1-待开奖
            ]);
            
            Db::commit();
            
            return out([
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticketNumber,
                'lottery_date' => $lotteryDate,
                'ticket_status' => 1,
                'ticket_status_text' => '待开奖',
                'draw_time' => $ticket->created_at,
            ], 200, '抽奖成功');
            
        } catch (Exception $e) {
            Db::rollback();
            return out(null, 10001, '抽奖失败：' . $e->getMessage());
        }
    }

    /**
     * 计算用户的摇号次数
     * 有捐款的用户，每天可以一次摇号
     * @return \think\response\Json
     */
    public function drawCount()
    {
        $user = $this->user;
        $userId = $user['id'];
        $lotteryDate = date('Y-m-d');
        
        // 检查用户是否捐款（只有捐款了的用户才能抽奖）
        $hasDonation = OrderTongxing::where('user_id', $userId)
            ->where('status', '>', 1)  // 已支付状态
            ->where('created_at','>','2025-12-13 00:00:00')
            ->where('pay_time', '>', 0)  // 有支付时间
            ->count();
        
        // 查询今天已摇号记录
        $todayTicket = NumberLotteryTicket::where('user_id', $userId)
            ->where('lottery_date', $lotteryDate)
            ->where('status', 1)
            ->find();
        
        $todayCount = $todayTicket ? 1 : 0;
        
        // 计算可摇号次数（有捐款的用户每天可以一次）
        $availableCount = 0;
        if ($hasDonation > 0) {
            $availableCount = max(0, 1 - $todayCount); // 每天最多1次，减去已摇号次数
        }
        
        // 查询总的中奖人数（不重复的用户数）
        $totalWinCount = NumberLotteryTicket::where('is_win', 1)
            ->where('status', 1)
            ->group('user_id')
            ->count();
        
        // 如果已经摇号，返回摇号结果
        $ticketInfo = null;
        if ($todayTicket) {
            $ticketInfo = [
                'id' => $todayTicket->id,
                'ticket_id' => $todayTicket->id,
                'ticket_number' => $todayTicket->ticket_number,
                'lottery_date' => $todayTicket->lottery_date,
                'is_win' => $todayTicket->is_win,
                'win_level' => $todayTicket->win_level,
                'win_prize' => $todayTicket->win_prize,
                'draw_id' => $todayTicket->draw_id,
                'ticket_status' => $todayTicket->ticket_status ?? 1,
                'ticket_status_text' => $todayTicket->ticket_status_text ?? '待开奖',
                'draw_time' => $todayTicket->created_at,
                'created_at' => $todayTicket->created_at,
            ];
        }
        
        return out([
            'has_donation' => $hasDonation > 0 ? 1 : 0,  // 是否有捐款：1-有，0-无
            'today_count' => $todayCount,  // 今天已摇号次数
            'available_count' => $availableCount,  // 今天可摇号次数
            'total_win_count' => $totalWinCount,  // 总的中奖人数
            'lottery_date' => $lotteryDate,  // 抽奖日期
            'ticket_info' => $ticketInfo,  // 今天的摇号结果（如果已摇号）
        ], 200, '查询成功');
    }

    
    
    /**
     * 生成抽奖号码（6位随机数字）
     * @return string 6位数字号码
     */
    private function generateTicketNumber()
    {
        // 直接生成6位随机数字（000000-999999）
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * 生成当天唯一的抽奖号码（6位随机数字）
     * @param string $lotteryDate 抽奖日期
     * @return string 6位数字号码（当天唯一）
     * @throws Exception
     */
    private function generateUniqueTicketNumber($lotteryDate)
    {
        $maxAttempts = 100; // 最大尝试次数，避免无限循环
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            // 生成6位随机数字（000000-999999）
            $ticketNumber = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // 检查当天是否已存在该号码
            $exists = NumberLotteryTicket::where('lottery_date', $lotteryDate)
                ->where('ticket_number', $ticketNumber)
                ->where('status', 1)
                ->count();
            
            if ($exists == 0) {
                // 号码唯一，返回
                return $ticketNumber;
            }
            
            $attempts++;
        }
        
        // 如果尝试100次都没找到唯一号码，抛出异常
        throw new Exception('生成唯一抽奖号码失败，请稍后重试');
    }
    
    /**
     * 我的抽奖记录列表
     * @return \think\response\Json
     */
    public function myTickets()
    {
        $req = request()->param();
        $user = $this->user;
        $userId = $user['id'];
        
        $builder = NumberLotteryTicket::where('user_id', $userId)
            ->order('id', 'desc');
        
        // 筛选条件
        if (isset($req['lottery_date']) && $req['lottery_date'] !== '') {
            $builder->where('lottery_date', $req['lottery_date']);
        }
        if (isset($req['is_win']) && $req['is_win'] !== '') {
            $builder->where('is_win', $req['is_win']);
        }
        
        // 分页
        $limit = isset($req['limit']) ? intval($req['limit']) : 20;
        $page = isset($req['page']) ? intval($req['page']) : 1;
        $data = $builder->paginate([
            'list_rows' => $limit,
            'page' => $page,
        ]);
        
        // 格式化数据
        $list = [];
        foreach ($data as $item) {
            $list[] = [
                'id' => $item->id,
                'ticket_number' => $item->ticket_number,
                'lottery_date' => $item->lottery_date,
                'is_win' => $item->is_win,
                'win_level' => $item->win_level,
                'win_prize' => $item->win_prize,
                'draw_id' => $item->draw_id,
                'ticket_status' => $item->ticket_status ?? 1,
                'ticket_status_text' => $item->ticket_status_text ?? '待开奖',
                'created_at' => $item->created_at,
            ];
        }
        
        return out([
            'list' => $list,
            'total' => $data->total(),
            'page' => $page,
            'limit' => $limit,
        ]);
    }
    
    /**
     * 开奖记录列表
     * @return \think\response\Json
     */
    public function drawList()
    {
        $req = request()->param();
        
        $builder = NumberLotteryDraw::order('draw_date', 'desc');
        
        // 筛选条件
        if (isset($req['draw_date']) && $req['draw_date'] !== '') {
            $builder->where('draw_date', $req['draw_date']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }
        
        // 分页
        $limit = isset($req['limit']) ? intval($req['limit']) : 20;
        $page = isset($req['page']) ? intval($req['page']) : 1;
        $data = $builder->paginate([
            'list_rows' => $limit,
            'page' => $page,
        ]);
        
        // 格式化数据
        $list = [];
        foreach ($data as $item) {
            $winningNumbers = [];
            if (!empty($item->winning_numbers)) {
                $winningNumbers = json_decode($item->winning_numbers, true) ?: [];
            }
            
            $list[] = [
                'id' => $item->id,
                'draw_date' => $item->draw_date,
                'winning_number' => $item->winning_number,
                'winning_numbers' => $winningNumbers,
                'draw_time' => $item->draw_time,
                'status' => $item->status,
                'status_text' => $this->getDrawStatusText($item->status),
                'total_tickets' => $item->total_tickets,
                'total_users' => $item->total_users,
                'win_count' => $item->win_count,
                'money' => $item->money,
                'remark' => $item->remark,
            ];
        }
        
        return out([
            'list' => $list,
            'total' => $data->total(),
            'page' => $page,
            'limit' => $limit,
        ]);
    }
    
    /**
     * 我的中奖记录
     * @return \think\response\Json
     */
    public function myWins()
    {
        $user = $this->user;
        $userId = $user['id'];
        
        $req = request()->param();
        
        $builder = NumberLotteryTicket::where('user_id', $userId)
            ->where('is_win', 1)
            ->order('id', 'desc');
        
        // 筛选条件
        if (isset($req['lottery_date']) && $req['lottery_date'] !== '') {
            $builder->where('lottery_date', $req['lottery_date']);
        }
        
        // 分页
        $limit = isset($req['limit']) ? intval($req['limit']) : 20;
        $page = isset($req['page']) ? intval($req['page']) : 1;
        $data = $builder->paginate([
            'list_rows' => $limit,
            'page' => $page,
        ]);
        
        // 格式化数据
        $list = [];
        foreach ($data as $item) {
            $list[] = [
                'id' => $item->id,
                'ticket_number' => $item->ticket_number,
                'lottery_date' => $item->lottery_date,
                'win_level' => $item->win_level,
                'win_prize' => $item->win_prize,
                'draw_id' => $item->draw_id,
                'ticket_status' => $item->ticket_status ?? 3,
                'ticket_status_text' => $item->ticket_status_text ?? '已中奖',
                'created_at' => $item->created_at,
            ];
        }
        
        return out([
            'list' => $list,
            'total' => $data->total(),
            'page' => $page,
            'limit' => $limit,
        ]);
    }
    
    /**
     * 查询今日抽奖次数
     * @return \think\response\Json
     */
    public function todayCount()
    {
        $user = $this->user;
        $userId = $user['id'];
        $lotteryDate = date('Y-m-d');
        
        $count = NumberLotteryTicket::where('user_id', $userId)
            ->where('lottery_date', $lotteryDate)
            ->where('status', 1)
            ->count();
        
        return out([
            'today_count' => $count,
            'lottery_date' => $lotteryDate,
        ]);
    }
    
    /**
     * 获取开奖状态文本
     * @param int $status
     * @return string
     */
    private function getDrawStatusText($status)
    {
        $statusMap = [
            0 => '未开奖',
            1 => '已开奖',
            2 => '已作废',
        ];
        return $statusMap[$status] ?? '未知';
    }
}


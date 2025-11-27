<?php

namespace app\api\controller;

use app\model\NumberLotteryTicket;
use app\model\NumberLotteryDraw;
use app\model\User;
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
        $currentHour = (int)date('H');
        
        // 检查是否在19点之后（19点及之后不能抽奖）
        if ($currentHour >= 19) {
            // return out(null, 10001, '每天19点后不能抽奖，请明天再来');
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
            // 生成抽奖号码（6位随机数字）
            $ticketNumber = $this->generateTicketNumber();
            
            // 创建抽奖记录
            $ticket = NumberLotteryTicket::create([
                'user_id' => $userId,
                'ticket_number' => $ticketNumber,
                'lottery_date' => $lotteryDate,
                'is_win' => 0,
                'status' => 1,
            ]);
            
            Db::commit();
            
            return out([
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticketNumber,
                'lottery_date' => $lotteryDate,
                'draw_time' => $ticket->created_at,
            ], 200, '抽奖成功');
            
        } catch (Exception $e) {
            Db::rollback();
            return out(null, 10001, '抽奖失败：' . $e->getMessage());
        }
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


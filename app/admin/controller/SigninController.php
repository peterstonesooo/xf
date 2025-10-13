<?php

namespace app\admin\controller;

use app\model\HongbaoSigninPrize;
use app\model\HongbaoSigninPrizeLog;
use app\model\LotteryUserSetting;
use app\model\TurntableSignPrize;
use app\model\UserSignin;
use app\model\User;
use app\model\UserSigninPrize;
use app\model\LotterySetting;
use app\model\LotteryRecord;
use app\model\Order;
use app\model\OrderDailyBonus;
use think\facade\Db;

class SigninController extends AuthController
{
    //签到奖励设置
    public function setting()
    {
        $req = request()->param();
        $builder = LotterySetting::order('id', 'desc');
        
        if (isset($req['type']) && $req['type'] !== '') {
            $builder->where('type', $req['type']);
        }
        
        $this->assign('data', $builder->select()->toArray());
        $this->assign('req', $req);
        return $this->fetch();  
    }

    public function signinPrizeAdd()
    {
        return $this->fetch();
    }

    //砸金蛋概率设置
    public function prizeSetting()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = LotterySetting::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    //砸金蛋概率设置提交
    public function editConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'number',
            'name|名称' => 'require',
            'type|类型' => 'require|in:1,2',
            'cash_amount|金额' => 'require',
            'win_probability|几率' => 'require',
            'cash_to_wallet|到账钱包' => 'require|in:team_bonus_balance,butie,gongfu_wallet',
        ]);

        if ($img_url = upload_file('img_url', false)) {
            $req['img_url'] = $img_url;
        }
        LotterySetting::where('id', $req['id'])->update($req);
        return out();
    }

    public function addConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'number',
            'name|名称' => 'require',
            'type|类型' => 'require|in:1,2',
            'cash_amount|金额' => 'require',
            'win_probability|几率' => 'require',
            'cash_to_wallet|到账钱包' => 'require|in:team_bonus_balance,butie,gongfu_wallet',
        ]);

        $req['img_url'] = upload_file('img_url');
        LotterySetting::create($req);

        return out();
    }

    //签到记录
    public function SigninLog()
    {
        $req = request()->param();
        $builder = UserSignin::order('id', 'desc');

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('user_id', $req['user_id']);
        }

        if (isset($req['phone']) && $req['phone'] !== '') {
            $user_id = User::where('phone', $req['phone'])->find();
            if ($user_id) {
                $builder->where('user_id', $user_id['id']);
            }
        }
        
        if (isset($req['signin_day']) && $req['signin_day'] !== '') {
            $builder->where('signin_date', $req['signin_day']);
        }
        
        // 添加时间范围查询
        if (isset($req['start_date']) && $req['start_date'] !== '') {
            $builder->where('signin_date', '>=', $req['start_date']);
        }
        
        if (isset($req['end_date']) && $req['end_date'] !== '') {
            $builder->where('signin_date', '<=', $req['end_date']);
        }
        
        // 新增：筛选在指定日期签到了但在另一个指定日期没有签到的人
        if (isset($req['signed_date']) && $req['signed_date'] !== '' && isset($req['not_signed_date']) && $req['not_signed_date'] !== '') {
            // 获取在signed_date签到的用户ID
            $signedUserIds = UserSignin::where('signin_date', $req['signed_date'])
                ->column('user_id');
            
            // 获取在not_signed_date签到的用户ID
            $notSignedUserIds = UserSignin::where('signin_date', $req['not_signed_date'])
                ->column('user_id');
            
            // 筛选出在signed_date签到了但在not_signed_date没有签到的用户
            $targetUserIds = array_diff($signedUserIds, $notSignedUserIds);
            
            if (!empty($targetUserIds)) {
                // 只显示在signed_date这天的签到记录
                $builder->whereIn('user_id', $targetUserIds)
                        ->where('signin_date', $req['signed_date']);
            } else {
                // 如果没有符合条件的用户，返回空结果
                $builder->where('id', 0); // 确保没有结果
            }
        }
        
        // 如果没有选择任何日期条件，默认显示当天数据
        if (empty($req['signin_day']) && empty($req['start_date']) && empty($req['end_date']) && empty($req['signed_date'])) {
            $today = date('Y-m-d');
            $builder->where('signin_date', $today);
        }
        
        $list = $builder->paginate(['query' => $req]);
        $this->assign('count', $builder->count());
        $this->assign('data', $list);
        $this->assign('req', $req);
        return $this->fetch();
    }
    
    // 导出签到记录Excel
    public function exportSigninLog()
    {
        // 设置内存限制和执行时间限制
        ini_set('memory_limit', '1G');
        set_time_limit(600); // 10分钟超时
        
        $req = request()->param();
        $builder = UserSignin::with('user')->order('id', 'desc');

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('user_id', $req['user_id']);
        }

        if (isset($req['phone']) && $req['phone'] !== '') {
            $user_id = User::where('phone', $req['phone'])->find();
            if ($user_id) {
                $builder->where('user_id', $user_id['id']);
            }
        }
        
        if (isset($req['signin_day']) && $req['signin_day'] !== '') {
            $builder->where('signin_date', $req['signin_day']);
        }
        
        // 添加时间范围查询
        if (isset($req['start_date']) && $req['start_date'] !== '') {
            $builder->where('signin_date', '>=', $req['start_date']);
        }
        
        if (isset($req['end_date']) && $req['end_date'] !== '') {
            $builder->where('signin_date', '<=', $req['end_date']);
        }
        
        // 新增：筛选在指定日期签到了但在另一个指定日期没有签到的人
        if (isset($req['signed_date']) && $req['signed_date'] !== '' && isset($req['not_signed_date']) && $req['not_signed_date'] !== '') {
            // 获取在signed_date签到的用户ID
            $signedUserIds = UserSignin::where('signin_date', $req['signed_date'])
                ->column('user_id');
            
            // 获取在not_signed_date签到的用户ID
            $notSignedUserIds = UserSignin::where('signin_date', $req['not_signed_date'])
                ->column('user_id');
            
            // 筛选出在signed_date签到了但在not_signed_date没有签到的用户
            $targetUserIds = array_diff($signedUserIds, $notSignedUserIds);
            
            if (!empty($targetUserIds)) {
                // 只显示在signed_date这天的签到记录
                $builder->whereIn('user_id', $targetUserIds)
                        ->where('signin_date', $req['signed_date']);
            } else {
                // 如果没有符合条件的用户，返回空结果
                $builder->where('id', 0); // 确保没有结果
            }
        }
        
        // 如果没有选择任何日期条件，默认导出当天数据
        if (empty($req['signin_day']) && empty($req['start_date']) && empty($req['end_date']) && empty($req['signed_date'])) {
            $today = date('Y-m-d');
            $builder->where('signin_date', $today);
        }
        
        try {
            $data = $builder->select()->toArray();
            
            // 记录导出信息
            \think\facade\Log::info('开始导出签到记录，数据量: ' . count($data) . '条');
            
            // 如果有筛选条件，添加说明
            $filterDesc = '';
            if (isset($req['signed_date']) && $req['signed_date'] !== '' && isset($req['not_signed_date']) && $req['not_signed_date'] !== '') {
                $filterDesc = '_在' . $req['signed_date'] . '签到但' . $req['not_signed_date'] . '未签到';
            }
            
            // 直接使用CSV格式导出，避免PhpSpreadsheet的复杂性
            $filename = '签到日志' . $filterDesc . '_' . date('Y-m-d_H-i-s') . '.csv';
            $this->exportToCsv($data, $filename);
            
        } catch (\Exception $e) {
            \think\facade\Log::error('导出签到记录失败: ' . $e->getMessage());
            \think\facade\Log::error('错误堆栈: ' . $e->getTraceAsString());
            
            // 返回错误信息
            return json(['code' => 500, 'msg' => '导出失败：' . $e->getMessage()]);
        }
    }
    
    // 格式化字节数
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    // CSV格式导出（备用方案）
    private function exportToCsv($data, $filename)
    {
        try {
            // 设置响应头
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            
            // 输出BOM以支持中文
            echo "\xEF\xBB\xBF";
            
            // 输出CSV数据
            $output = fopen('php://output', 'w');
            
            // 写入表头
            fputcsv($output, ['ID', '用户ID', '用户手机号', '签到日期', '连续天数', '是否补签', '签到时间']);
            
            // 分批写入数据以避免内存问题
            $batchSize = 1000;
            $totalRows = count($data);
            
            for ($i = 0; $i < $totalRows; $i += $batchSize) {
                $batch = array_slice($data, $i, $batchSize);
                
                foreach ($batch as $item) {
                    fputcsv($output, [
                        $item['id'],
                        $item['user_id'],
                        $item['user']['phone'] ?? '',
                        $item['signin_date'],
                        $item['continue_days'],
                        $item['signin_back'] == 0 ? '签到' : '补签',
                        $item['created_at']
                    ]);
                }
                
                // 每批处理后检查内存
                if ($i % ($batchSize * 2) == 0) {
                    $currentMemory = memory_get_usage(true);
                    \think\facade\Log::info('CSV处理进度: ' . ($i + $batchSize) . '/' . $totalRows . ', 内存使用: ' . $this->formatBytes($currentMemory));
                }
            }
            
            fclose($output);
            exit;
            
        } catch (\Exception $e) {
            \think\facade\Log::error('CSV导出失败: ' . $e->getMessage());
            \think\facade\Log::error('错误堆栈: ' . $e->getTraceAsString());
            
            // 如果CSV导出失败，返回错误信息
            return json(['code' => 500, 'msg' => 'CSV导出失败：' . $e->getMessage()]);
        }
    }
    
    public function del()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        LotterySetting::destroy($req['id']);
        return out();
    }

    public function SigninPrizeLog()
    {
        $req = request()->param();
        $builder = LotteryRecord::alias('l')
            ->join('mp_user u', 'l.user_id = u.id')
            ->join('mp_lottery_setting s', 'l.lottery_id = s.id')
            ->field('l.*, u.phone, s.name as prize_name')
            ->order('l.id', 'desc');

        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', $req['phone']);
        }

        if (isset($req['lottery_id']) && $req['lottery_id'] !== '') {
            $builder->where('l.lottery_id', $req['lottery_id']);
        }

        if (isset($req['receive']) && $req['receive'] !== '') {
            $builder->where('l.receive', $req['receive']);
        }

        $list = $builder->paginate(['query' => $req]);
        $prize = LotterySetting::select();
        $this->assign('count', $builder->count());
        $this->assign('prize', $prize);
        $this->assign('data', $list);
        $this->assign('req', $req);
        return $this->fetch();
    }

    public function luckyUser()
    {
        $req = request()->param();

        $builder = LotteryUserSetting::order(['id' => 'desc']);

        $data = $builder->paginate(['query' => $req]);
        $groups = config('map.project.group');
        $this->assign('groups',$groups);
        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function luckyUserAdd()
    {
        $prizeList = LotterySetting::order('id asc')->select();
        $this->assign('prizeList', $prizeList);
        return $this->fetch();
    }

    //修改、添加内定
    public function hongbaoUserAdd()
    {
        $req = $this->validate(request(), [
            'prize_id|奖品名' => 'number',
            'phone|奖品名' => 'number',
        ]);

        $user = User::where('phone',$req['phone'])->find();
        if (empty($user)){
            exit_out(null, 10090, '请输入正确的电话号码');
        }

        $prize = LotterySetting::where('id',$req['prize_id'])->find();
        if (empty($prize)){
            exit_out(null, 10090, '没找到对应的奖品');
        }

        if (!empty($req['id'])) {
            $req['user_id'] = $user['id'];
            $req['phone'] = $user['phone'];
            $req['prize_id'] = $prize['id'];
            $req['prize_name'] = $user['name'];
            LotteryUserSetting::where('id', $req['id'])->update($req);
        } else {
            LotteryUserSetting::insert([
                'user_id' => $user['id'],
                'phone' => $user['phone'],
                'prize_name' => $prize['name'],
                'prize_id' => $prize['id'],
                'status' => 0,
            ]);
        }
        return out();
    }

    //编辑
    public function changeLuckyStatus()
    {
        $req = $this->validate(request(), [
            'id|id' => 'number'
        ]);

        $req['status'] = 1;
        LotteryUserSetting::where('id',$req['id'])->update($req);
        return out();
    }

    //删除
    public function luckyDelete()
    {
        $req = $this->validate(request(), [
            'id|id' => 'number'
        ]);

        LotteryUserSetting::where('id',$req['id'])->delete();
        return out();
    }

    /**
     * 一键更新用户订单抽奖次数
     * 计算逻辑：
     * 1. 统计用户在mp_order和mp_order_daily_bonus表中的有效订单总数
     * 2. 减去用户在mp_lottery_record表中order_id>0的记录数（已使用的抽奖次数）
     * 3. 更新到mp_user表的order_lottery_tickets字段
     */
    public function updateLotteryTickets()
    {
            $successCount = 0;
            $failCount = 0;
            $updated_count = 0;

            // 使用 chunk 方法分批处理数据
            User::where('id','>',0)->order('id', 'asc')->chunk(500, function($orders) use (&$successCount, &$failCount,&$output) {
                foreach ($orders as $order) {
                    Db::startTrans();
                    try {
                        $order_count = Order::where('user_id',$order['id'])->where('status','>',1)->count()+OrderDailyBonus::where('user_id',$order['id'])->where('status','>',1)->count();
                        $lottery_count = LotteryRecord::where('user_id',$order['id'])->where('order_id','>',0)->count();
                        $order_lottery_tickets = $order_count - $lottery_count;
                        if($order['order_lottery_tickets'] != $order_lottery_tickets){
                            User::where('id',$order['id'])->update(['order_lottery_tickets'=>$order_lottery_tickets]);
                            $updated_count++;
                        }
                        Db::commit();
                        $successCount++;
                    } catch (\Exception $e) {
                        Db::rollback();
                        $failCount++;
                    }
                }
            });

        return out([
            'updated_count' => $updated_count,
            'fail_count' => $failCount
        ]);
    }
}

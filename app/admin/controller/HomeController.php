<?php

namespace app\admin\controller;

use app\model\Capital;
use app\model\Order;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserSignin;
use app\model\OrderTiyan;
use app\model\OrderTongxing;
use app\model\YuanmengUser;
use app\model\OrderDailyBonus;
use app\model\Project;
use app\model\HappinessEquityActivation;
use app\model\UserActive;
use app\model\VipLog;

class HomeController extends AuthController
{
    public function index()
    {
        // 检查用户是否有控制台权限，如果有就显示数据
        $adminUser = session('admin_user');
        if(!$adminUser){
            $this->assign('data', []);
            return $this->fetch();
        }

        $today = date("Y-m-d 00:00:00", time());
        $yesterday = date("Y-m-d 00:00:00", strtotime("-1 day"));
        $yesterday_end = date("Y-m-d 23:59:59", strtotime("-1 day"));

        $data = $arr = [];

        $arr['title'] = '注册总会员数';
        $arr['value'] = User::count();
        $arr['title1'] = '今日注册会员数';
        $arr['value1'] = User::where('created_at', '>=', $today)->count();
        $arr['title2'] = '昨日注册会员数';
        $arr['value2'] = User::where('created_at', '>=', $yesterday)->where('created_at', '<=', $yesterday_end)->count();
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '激活总人数';
        $arr['value'] = $this->getWufuActiveUsers();
        $arr['title1'] = '今日激活人数';
        $arr['value1'] = $this->getWufuActiveUsers($today);
        $arr['title2'] = '昨日激活人数';
        $arr['value2'] = $this->getWufuActiveUsers($yesterday, $yesterday_end);
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '申领总人数';
        $order_users = Order::where('status', '>', 1)->column('user_id');
        $daily_bonus_users = OrderDailyBonus::where('status', '>', 1)->column('user_id');
        $arr['value'] = count(array_unique(array_merge($order_users, $daily_bonus_users)));

        $arr['title1'] = '今日申领人数';
        $today_order_users = Order::where('status', '>', 1)->where('pay_time', '>=', strtotime($today))->column('user_id');
        $today_daily_bonus_users = OrderDailyBonus::where('status', '>', 1)->where('pay_time', '>=', strtotime($today))->column('user_id');
        $arr['value1'] = count(array_unique(array_merge($today_order_users, $today_daily_bonus_users)));
        
        $arr['title2'] = '昨日申领人数';
        $yesterday_order_users = Order::where('status', '>', 1)->where('pay_time', '>=', strtotime($yesterday))->where('pay_time', '<=', strtotime($yesterday_end))->column('user_id');
        $yesterday_daily_bonus_users = OrderDailyBonus::where('status', '>', 1)->where('pay_time', '>=', strtotime($yesterday))->where('pay_time', '<=', strtotime($yesterday_end))->column('user_id');
        $arr['value2'] = count(array_unique(array_merge($yesterday_order_users, $yesterday_daily_bonus_users)));
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '申领总份额';
        $order_users = Order::where('status', '>', 1)->column('user_id');
        $daily_bonus_users = OrderDailyBonus::where('status', '>', 1)->column('user_id');
        $arr['value'] = count(array_merge($order_users, $daily_bonus_users));
        
        $arr['title1'] = '今日申领份额';
        $today_order_users = Order::where('status', '>', 1)->where('pay_time', '>=', strtotime($today))->column('user_id');
        $today_daily_bonus_users = OrderDailyBonus::where('status', '>', 1)->where('pay_time', '>=', strtotime($today))->column('user_id');
        $arr['value1'] = count(array_merge($today_order_users, $today_daily_bonus_users));
        
        $arr['title2'] = '昨日申领份额';
        $yesterday_order_users = Order::where('status', '>', 1)->where('pay_time', '>=', strtotime($yesterday))->where('pay_time', '<=', strtotime($yesterday_end))->column('user_id');
        $yesterday_daily_bonus_users = OrderDailyBonus::where('status', '>', 1)->where('pay_time', '>=', strtotime($yesterday))->where('pay_time', '<=', strtotime($yesterday_end))->column('user_id');
        $arr['value2'] = count(array_merge($yesterday_order_users, $yesterday_daily_bonus_users));
        $arr['url'] = '';
        $data[] = $arr;

        

        $arr['title'] = '充值总金额';
        $arr['value'] = round(Capital::where('status', 2)->whereIn('type', [1, 3])->sum('amount'), 2);
        $arr['title1'] = '今日充值总金额';
        $arr['value1'] = round(Capital::where('status', 2)->whereIn('type', [1, 3])->where('created_at', '>=', $today)->sum('amount'), 2);
        $arr['title2'] = '昨日充值总金额';
        $arr['value2'] = round(Capital::where('status', 2)->whereIn('type', [1, 3])->where('created_at', '>=', $yesterday)->where('created_at', '<=', $yesterday_end)->sum('amount'), 2);
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '充值总次数';
        $arr['value'] = Capital::where('status', 2)->whereIn('type', [1, 3])->count();
        $arr['title1'] = '今日充值总次数';
        $arr['value1'] = Capital::where('status', 2)->whereIn('type', [1, 3])->where('created_at', '>=', $today)->count();
        $arr['title2'] = '昨日充值总次数';
        $arr['value2'] = Capital::where('status', 2)->whereIn('type', [1, 3])->where('created_at', '>=', $yesterday)->where('created_at', '<=', $yesterday_end)->count();
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '体验总会员数';
        $arr['value'] = OrderTiyan::count();
        $arr['title1'] = '今日体验会员数';
        $arr['value1'] = OrderTiyan::where('pay_time', '>=', strtotime($today))->count();
        $arr['title2'] = '昨日体验会员数';
        $arr['value2'] = OrderTiyan::where('pay_time', '>=', strtotime($yesterday))->where('pay_time', '<=', strtotime($yesterday_end))->count();
        $arr['url'] = '';
        $data[] = $arr;

        $signin_date = date('Y-m-d');
        $yesterday_signin_date = date('Y-m-d', strtotime("-1 day"));
        $arr['title'] = '签到记录数量';
        $arr['value'] = UserSignin::count();
        $arr['title1'] = '今日签到记录数';
        $arr['value1'] = UserSignin::where('signin_date', $signin_date)->count();
        $arr['title2'] = '昨日签到记录数';
        $arr['value2'] = UserSignin::where('signin_date', $yesterday_signin_date)->count();
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '提现总金额';
        $arr['value'] = round(0 - Capital::where('status', 2)->where('type', 2)->sum('amount'), 2);
        $arr['title1'] = '今日提现总金额';
        $arr['value1'] = round(0 - Capital::where('status', 2)->where('type', 2)->where('created_at', '>=', $today)->sum('amount'), 2);
        $arr['title2'] = '昨日提现总金额';
        $arr['value2'] = round(0 - Capital::where('status', 2)->where('type', 2)->where('created_at', '>=', $yesterday)->where('created_at', '<=', $yesterday_end)->sum('amount'), 2);
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '提现总次数';
        $arr['value'] = Capital::where('status', 2)->where('type', 2)->count();
        $arr['title1'] = '今日提现总次数';
        $arr['value1'] = Capital::where('status', 2)->where('type', 2)->where('created_at', '>=', $today)->count();
        $arr['title2'] = '昨日提现总次数';
        $arr['value2'] = Capital::where('status', 2)->where('type', 2)->where('created_at', '>=', $yesterday)->where('created_at', '<=', $yesterday_end)->count();
        $arr['url'] = '';
        $data[] = $arr;

        $arr['title'] = '投资总金额';
        $arr['value'] = round(abs(UserBalanceLog::whereIn('type', [3,62])->where('log_type', 1)->sum('change_balance')), 2);
        $arr['title1'] = '今日投资总金额';
        $arr['value1'] = round(abs(UserBalanceLog::whereIn('type', [3,62])->where('log_type', 1)->where('created_at', '>=', $today)->sum('change_balance')), 2);
        $arr['title2'] = '昨日投资总金额';
        $arr['value2'] = round(abs(UserBalanceLog::whereIn('type', [3,62])->where('log_type', 1)->where('created_at', '>=', $yesterday)->where('created_at', '<=', $yesterday_end)->sum('change_balance')), 2);
        $arr['url'] = '';
        $data[] = $arr;

        // $weekday = date('w');
        // $allowed_group_id = $weekday + 6;
        // if ($weekday >= 1 && $weekday <= 5 ) {
        //     $today_projescs = Project::where('project_group_id', $allowed_group_id)->where('status', 1)->column('id');
        // }else{
        //     $today_projescs = Project::where('status', 1)->column('id');
        // }
        
        // 统计VIP开通数据
        $today_start = strtotime(date('Y-m-d 00:00:00'));
        $today_end = strtotime(date('Y-m-d 23:59:59'));
        $yesterday_start = strtotime($yesterday);
        $yesterday_end_ts = strtotime($yesterday_end);

        $vipQuery = VipLog::where('status', 1)
            ->where('pay_time', '>', 0);

        $arr['title'] = '开通总人数';
        $arr['value'] = (clone $vipQuery)->group('user_id')->count();

        $arr['title1'] = '今日开通人数';
        $arr['value1'] = (clone $vipQuery)
            ->where('pay_time', '>=', $today_start)
            ->where('pay_time', '<=', $today_end)
            ->group('user_id')
            ->count();

        $arr['title2'] = '昨日开通人数';
        $arr['value2'] = (clone $vipQuery)
                ->where('pay_time', '>=', $yesterday_start)
                ->where('pay_time', '<=', $yesterday_end_ts)
                ->group('user_id')
                ->count();
        $arr['url'] = '';
        $data[] = $arr;

        $this->assign('data', $data);

        return $this->fetch();
    }

    public function uploadSummernoteImg()
    {
        $img_url = upload_file2('img_url',true,false);

        return out(['img_url' => env('app.img_host').$img_url, 'filename' => md5(time()).'.jpg']);
    }

    /**
     * 获取五福临门激活用户数量
     * @param string|null $startDate 开始日期
     * @param string|null $endDate 结束日期
     * @return int 激活用户数量
     */
    private function getWufuActiveUsers($startDate = null, $endDate = null)
    {
        // 从2025-09-21 00:00:00开始统计
        if(empty($startDate)) {
            $startDate = '2025-09-21 00:00:00';
        }
        
        // 使用 mp_user_active 表查询激活用户
        $query = UserActive::where('is_active', 1);

        // 如果有日期范围限制
        if ($startDate && $endDate) {
            $query->where('active_time', '>=', strtotime($startDate))->where('active_time', '<=', strtotime($endDate));
        } elseif ($startDate) {
            $query->where('active_time', '>=', strtotime($startDate));
        }

        return $query->count();
    }
}
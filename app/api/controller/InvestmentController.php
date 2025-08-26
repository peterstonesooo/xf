<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\controller\AuthController;
use app\model\InvestmentGradient;
use app\model\InvestmentRecord;
use app\model\InvestmentReturnRecord;
use app\model\LoanConfig;
use app\model\User;
use app\model\UserBalanceLog;
use think\facade\Db;
use think\facade\Log;

class InvestmentController extends AuthController
{
    /**
     * 获取出资梯度信息
     */
    public function getGradient()
    {
        try {
            $req = $this->validate(request(), [
                'gradient_id' => 'require|number'
            ]);

            $gradient = InvestmentGradient::where('id', $req['gradient_id'])
                                         ->where('status', 1)
                                         ->find();

            if (!$gradient) {
                return out(null, 404, '出资梯度不存在或已禁用');
            }

            $data = [
                'id' => $gradient->id,
                'name' => $gradient->name,
                'investment_days' => $gradient->investment_days,
                'interest_rate' => $gradient->interest_rate,
                'min_amount' => $gradient->min_amount,
                'max_amount' => $gradient->max_amount,
                'status' => $gradient->status
            ];

            return out($data, 0, '获取成功');

        } catch (\Exception $e) {
            return out(null, 500, '获取梯度信息失败：' . $e->getMessage());
        }
    }

    /**
     * 获取出资梯度列表
     */
    public function getGradientList()
    {
        try {
            $gradients = InvestmentGradient::where('status', 1)
                                          ->order('sort asc, id asc')
                                          ->select();

            $data = [];
            foreach ($gradients as $gradient) {
                $data[] = [
                    'id' => $gradient->id,
                    'name' => $gradient->name,
                    'investment_days' => $gradient->investment_days,
                    'interest_rate' => $gradient->interest_rate,
                    'min_amount' => $gradient->min_amount,
                    'max_amount' => $gradient->max_amount,
                    'status' => $gradient->status
                ];
            }

            return out($data, 0, '获取成功');

        } catch (\Exception $e) {
            return out(null, 500, '获取梯度列表失败：' . $e->getMessage());
        }
    }

    /**
     * 提交出资申请
     */
    public function submitInvestment()
    {
        try {
            $req = $this->validate(request(), [
                'gradient_id' => 'require|number',
                'investment_amount' => 'require|float|gt:0',
                'wallet_type' => 'require|number|in:1,2,3,4,5,6,7,8,9,10,11,12',
                'pay_password' => 'require'
            ]);

            // 验证支付密码
            if ($this->user['pay_password'] !== md5($req['pay_password'])) {
                return out(null, 400, '支付密码错误');
            }

            // 获取梯度信息
            $gradient = InvestmentGradient::where('id', $req['gradient_id'])
                                         ->where('status', 1)
                                         ->find();
            if (!$gradient) {
                return out(null, 404, '出资梯度不存在或已禁用');
            }

            // 验证出资金额
            if ($req['investment_amount'] < $gradient->min_amount || $req['investment_amount'] > $gradient->max_amount) {
                return out(null, 400, '出资金额必须在' . $gradient->min_amount . '到' . $gradient->max_amount . '之间');
            }

            // 验证钱包类型是否支持
            $supportedWalletTypes = LoanConfig::getConfig('investment_wallet_types', '1,2,3,4,5');
            $supportedWalletTypes = explode(',', $supportedWalletTypes);
            if (!in_array($req['wallet_type'], $supportedWalletTypes)) {
                return out(null, 400, '不支持的钱包类型');
            }

            // 获取钱包字段名
            $walletFieldMap = [
                1 => 'topup_balance',
                2 => 'team_bonus_balance',
                3 => 'butie',
                4 => 'balance',
                5 => 'digit_balance',
                6 => 'integral',
                7 => 'appreciating_wallet',
                8 => 'butie_lock',
                9 => 'lottery_tickets',
                10 => 'tiyan_wallet_lock',
                11 => 'tiyan_wallet',
                12 => 'xingfu_tickets'
            ];

            $walletField = $walletFieldMap[$req['wallet_type']] ?? 'topup_balance';

            // 检查用户余额
            $user = User::where('id', $this->user['id'])->find();
            if ($user[$walletField] < $req['investment_amount']) {
                return out(null, 400, '钱包余额不足');
            }

            // 计算利息和总金额
            $interestRate = $gradient->interest_rate;
            $investmentDays = $gradient->investment_days;
            
            // 直接使用总利息率计算
            $totalInterest = bcmul($req['investment_amount'], $interestRate, 2);
            $totalAmount = bcadd($req['investment_amount'], $totalInterest, 2);

            // 计算开始和结束日期
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime("+{$investmentDays} days"));

            Db::startTrans();
            try {
                // 扣除用户钱包余额
                User::where('id', $this->user['id'])->dec($walletField, $req['investment_amount'])->update();

                // 记录余额变动日志
                $logType = $req['wallet_type'] == 6 ? 2 : 1; // 积分用log_type=2
                UserBalanceLog::create([
                    'user_id' => $this->user['id'],
                    'type' => 113, // 出资扣款
                    'log_type' => $logType,
                    'relation_id' => 0,
                    'before_balance' => $user[$walletField],
                    'change_balance' => -$req['investment_amount'],
                    'after_balance' => bcsub($user[$walletField], $req['investment_amount'], 2),
                    'remark' => '出资扣款',
                    'status' => 2
                ]);

                // 创建出资记录
                $investment = InvestmentRecord::create([
                    'user_id' => $this->user['id'],
                    'gradient_id' => $req['gradient_id'],
                    'investment_amount' => $req['investment_amount'],
                    'investment_days' => $investmentDays,
                    'interest_rate' => $interestRate,
                    'total_interest' => $totalInterest,
                    'total_amount' => $totalAmount,
                    'wallet_type' => $req['wallet_type'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 1 // 进行中
                ]);

                Db::commit();

                // 返回出资信息
                $result = [
                    'id' => $investment->id,
                    'gradient_name' => $gradient->name,
                    'investment_amount' => $investment->investment_amount,
                    'investment_days' => $investment->investment_days,
                    'interest_rate' => $investment->interest_rate,
                    'total_interest' => $investment->total_interest,
                    'total_amount' => $investment->total_amount,
                    'start_date' => $investment->start_date,
                    'end_date' => $investment->end_date,
                    'status' => $investment->status,
                    'status_text' => $investment->getStatusTextAttr(null, $investment->toArray())
                ];

                return out($result, 0, '出资成功');

            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return out(null, 500, '出资失败：' . $e->getMessage());
        }
    }

    /**
     * 获取我的出资记录
     */
    public function getMyInvestments()
    {
        try {
            $req = $this->validate(request(), [
                'page' => 'number|default:1',
                'limit' => 'number|default:10',
                'status' => 'number|in:1,2,3'
            ]);
            
            $user = $this->user;
            
            $builder = InvestmentRecord::with(['gradient'])
                                      ->where('user_id', $user['id']);
            
            if (isset($req['status'])) {
                $builder->where('status', $req['status']);
            }
            
            $investments = $builder->order('id desc')
                                  ->paginate([
                                      'list_rows' => $req['limit'],
                                      'page' => $req['page']
                                  ]);

            $data = [];
            foreach ($investments as $investment) {
                $data[] = [
                    'id' => $investment->id,
                    'gradient_name' => $investment->gradient->name ?? '',
                    'investment_amount' => $investment->investment_amount,
                    'investment_days' => $investment->investment_days,
                    'interest_rate' => $investment->interest_rate,
                    'total_interest' => $investment->total_interest,
                    'total_amount' => $investment->total_amount,
                    'wallet_type' => $investment->wallet_type,
                    'wallet_type_text' => $investment->getWalletTypeTextAttr(null, $investment->toArray()),
                    'start_date' => $investment->start_date,
                    'end_date' => $investment->end_date,
                    'status' => $investment->status,
                    'status_text' => $investment->getStatusTextAttr(null, $investment->toArray()),
                    'return_time' => $investment->return_time,
                    'created_at' => $investment->created_at
                ];
            }

            return out([
                'list' => $data,
                'total' => $investments->total(),
                'page' => $investments->currentPage(),
                'limit' => $investments->listRows()
            ], 0, '获取成功');

        } catch (\Exception $e) {
            return out(null, 500, '获取出资记录失败：' . $e->getMessage());
        }
    }

    /**
     * 获取支持的出资钱包类型
     */
    public function getSupportedWalletTypes()
    {
        try {
            // 钱包类型映射
            $walletTypeMap = [
                1 => ['field' => 'topup_balance', 'name' => '充值余额'],
                2 => ['field' => 'team_bonus_balance', 'name' => '荣誉钱包'],
                3 => ['field' => 'butie', 'name' => '稳盈钱包'],
                4 => ['field' => 'balance', 'name' => '民生钱包'],
                5 => ['field' => 'digit_balance', 'name' => '收益钱包'],
                6 => ['field' => 'integral', 'name' => '积分'],
                7 => ['field' => 'appreciating_wallet', 'name' => '幸福收益'],
                8 => ['field' => 'butie_lock', 'name' => '稳赢钱包转入'],
                9 => ['field' => 'lottery_tickets', 'name' => '抽奖卷'],
                10 => ['field' => 'tiyan_wallet_lock', 'name' => '体验钱包预支金'],
                11 => ['field' => 'tiyan_wallet', 'name' => '体验钱包'],
                12 => ['field' => 'xingfu_tickets', 'name' => '幸福助力卷'],
            ];

            // 获取支持的钱包类型配置
            $supportedTypes = LoanConfig::getConfig('investment_wallet_types', '1,2,3,4,5');
            $supportedTypes = explode(',', $supportedTypes);

            $data = [];
            foreach ($supportedTypes as $type) {
                $type = (int)trim($type);
                if (isset($walletTypeMap[$type])) {
                    $data[] = [
                        'type' => $type,
                        'name' => $walletTypeMap[$type]['name'],
                        'balance' => $this->user[$walletFieldMap[$type]['field']] ?? 0
                    ];
                }
            }

            return out($data, 0, '获取成功');

        } catch (\Exception $e) {
            return out(null, 500, '获取钱包类型失败：' . $e->getMessage());
        }
    }

    /**
     * 获取出资统计信息
     */
    public function getInvestmentStats()
    {
        try {
            $user = $this->user;
            
            // 总出资次数
            $totalCount = InvestmentRecord::where('user_id', $user['id'])->count();
            
            // 进行中的出资
            $activeCount = InvestmentRecord::where('user_id', $user['id'])->where('status', 1)->count();
            
            // 总出资金额
            $totalInvestment = InvestmentRecord::where('user_id', $user['id'])->sum('investment_amount');
            
            // 进行中的出资金额
            $activeInvestment = InvestmentRecord::where('user_id', $user['id'])->where('status', 1)->sum('investment_amount');
            
            // 总收益
            $totalReturn = InvestmentReturnRecord::where('user_id', $user['id'])->sum('interest_amount');
            
            // 今日收益
            $todayReturn = InvestmentReturnRecord::where('user_id', $user['id'])
                                                ->where('created_at', '>=', date('Y-m-d 00:00:00'))
                                                ->sum('interest_amount');

            $data = [
                'total_count' => $totalCount,
                'active_count' => $activeCount,
                'total_investment' => $totalInvestment,
                'active_investment' => $activeInvestment,
                'total_return' => $totalReturn,
                'today_return' => $todayReturn
            ];

            return out($data, 0, '获取成功');

        } catch (\Exception $e) {
            return out(null, 500, '获取统计信息失败：' . $e->getMessage());
        }
    }
}

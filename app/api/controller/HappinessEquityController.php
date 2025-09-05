<?php

namespace app\api\controller;

use app\model\HappinessEquityActivation;
use app\model\User;
use app\model\Order;
use app\model\OrderDailyBonus;
use app\model\OrderTiyan;
use app\model\OrderTongxing;
use app\model\PointsOrder;
use app\model\CoinOrder;
use app\model\InvestmentRecord;
use app\model\LoanApplication;
use app\model\RelationshipRewardLog;
use app\model\UserRelation;
use think\facade\Db;
use Exception;

class HappinessEquityController extends AuthController
{
    /**
     * 获取幸福权益激活信息
     */
    public function getActivationInfo()
    {
        try {
            $user = $this->user;
            
            // 检查用户是否已激活
            $activation = HappinessEquityActivation::getUserActivation($user['id']);
            
            // 检查用户是否有购买产品
            $hasPurchased = $this->checkUserHasPurchased($user['id']);
            
            // 根据是否有购买产品确定权益保障比例
            if ($hasPurchased) {
                $equityRate = 1.00; // 有购买产品：1%
                $title = '幸福权益保障激活';
            } else {
                $equityRate = 1.50; // 无购买产品：1.5%
                $title = '民生权益保障激活';
            }
            
            // 重新获取完整的用户数据
            $user = User::where('id', $user['id'])->find();
            
            // 计算民生钱包和稳盈钱包的总余额
            $totalBalance = $this->calculateTwoWalletBalance($user);
            
            // 计算需要缴纳的金额
            $paymentAmount = round($totalBalance * ($equityRate / 100), 2);
            
            // 如果已激活，使用激活记录中的实际缴纳金额
            if ($activation) {
                $paymentAmount = $activation['payment_amount'];
                $equityRate = $activation['equity_rate'];
                $title = $activation['title'];
            }
            
            $data = [
                'is_activated' => !empty($activation),
                'title' => $title,
                'equity_rate' => $equityRate,
                'equity_rate_text' => '权益保障比例：' . $equityRate . '%',
                'total_balance' => $totalBalance,
                'payment_amount' => $paymentAmount,
                'wallet_balances' => [
                    'balance' => $user['balance'], // 民生钱包
                    'butie' => $user['butie'], // 稳盈钱包
                    'butie_lock' => $user['butie_lock'] // 稳盈钱包转入
                ]
            ];
            
            // 如果已激活，添加激活信息
            if ($activation) {
                $data['activation_info'] = [
                    'activation_sn' => $activation['activation_sn'],
                    'activation_time' => $activation['created_at'],
                    'activation_amount' => $activation['payment_amount']
                ];
            }
            
            return out($data, 0, '获取成功');
            
        } catch (Exception $e) {
            return out(null, 500, '获取激活信息失败：' . $e->getMessage());
        }
    }
    
    /**
     * 提交幸福权益激活
     */
    public function submitActivation()
    {
        try {
            $user = $this->user;
            
            // 检查用户是否已激活
            $existingActivation = HappinessEquityActivation::getUserActivation($user['id']);
            if ($existingActivation) {
                return out(null, 10001, '您已激活幸福权益，无需重复激活');
            }
            
            // 检查用户是否有购买产品
            $hasPurchased = $this->checkUserHasPurchased($user['id']);
            
            // 根据是否有购买产品确定权益保障比例
            if ($hasPurchased) {
                $equityRate = 1.00; // 有购买产品：1%
            } else {
                $equityRate = 1.50; // 无购买产品：1.5%
            }
            
            // 重新获取完整的用户数据
            $user = User::where('id', $user['id'])->find();
            
            // 计算民生钱包和稳盈钱包的总余额
            $totalBalance = $this->calculateTwoWalletBalance($user);
            
            // 计算需要缴纳的金额
            $paymentAmount = round($totalBalance * ($equityRate / 100), 2);
            
            // 检查充值余额是否足够
            if ($user['topup_balance'] < $paymentAmount) {
                return out(null, 10001, '充值余额不足，需要' . $paymentAmount . '元');
            }
            
            Db::startTrans();
            try {
                // 重新获取用户信息（加锁）
                $user = User::where('id', $user['id'])->lock(true)->find();
                
                // 再次检查余额
                if ($user['topup_balance'] < $paymentAmount) {
                    return out(null, 10001, '充值余额不足，需要' . $paymentAmount . '元');
                }
                
                // 记录缴纳前的充值余额
                $beforeTopupBalance = $user['topup_balance'];
                
                // 扣除充值余额
                User::changeInc($user['id'], -$paymentAmount, 'topup_balance', 115, 0, 1, '幸福权益激活', 0, 1);
                
                // 准备钱包余额数据
                $walletBalances = [
                    'balance' => $user['balance'], // 民生钱包
                    'butie' => $user['butie'], // 稳盈钱包
                    'butie_lock' => $user['butie_lock'] // 稳盈钱包转入
                ];
                
                $afterTopupBalance = $beforeTopupBalance - $paymentAmount;
                
                // 创建激活记录
                $activation = HappinessEquityActivation::createActivation(
                    $user['id'], 
                    $equityRate, 
                    $walletBalances, 
                    $totalBalance, 
                    $paymentAmount, 
                    $beforeTopupBalance, 
                    $afterTopupBalance
                );
                
                // 分发三级返回
                $this->distributeThreeLevelReward($user['id'], $paymentAmount, $activation['id']);
                
                Db::commit();
                
                return out([
                    'activation_sn' => $activation['activation_sn'],
                    'payment_amount' => $paymentAmount,
                    'equity_rate' => $equityRate
                ], 0, '幸福权益激活成功');
                
            } catch (Exception $e) {
                Db::rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            return out(null, 500, '激活失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取激活记录
     */
    public function getActivationLogs()
    {
        try {
            $req = $this->validate(request(), [
                'page|页码' => 'number|default:1',
                'limit|每页数量' => 'number|default:10'
            ]);
            
            $user = $this->user;
            $logs = HappinessEquityActivation::getUserLogs($user['id'], $req['page'], $req['limit']);
            
            return out($logs, 0, '获取成功');
            
        } catch (Exception $e) {
            return out(null, 500, '获取记录失败：' . $e->getMessage());
        }
    }
    
    /**
     * 检查用户是否有购买产品（只检查产品组7,8,9,10,11）
     */
    private function checkUserHasPurchased($userId)
    {
        // 只检查产品组7,8,9,10,11的订单
        $targetGroups = [7, 8, 9, 10, 11];
        
        // 检查各种订单表，通过项目表关联产品组进行过滤
        $orderCount = Order::alias('o')
            ->join('mp_project p', 'o.project_id = p.id')
            ->where('o.user_id', $userId)
            ->whereIn('o.status', [2, 3, 4])
            ->whereIn('p.project_group_id', $targetGroups)
            ->count();
            
        $dailyBonusCount = OrderDailyBonus::alias('o')
            ->join('mp_project p', 'o.project_id = p.id')
            ->where('o.user_id', $userId)
            ->whereIn('o.status', [2, 3, 4])
            ->whereIn('p.project_group_id', $targetGroups)
            ->count();
            
        $tiyanCount = OrderTiyan::alias('o')
            ->join('mp_project p', 'o.project_id = p.id')
            ->where('o.user_id', $userId)
            ->whereIn('o.status', [2, 3, 4])
            ->whereIn('p.project_group_id', $targetGroups)
            ->count();
            
        $tongxingCount = OrderTongxing::alias('o')
            ->join('mp_project p', 'o.project_id = p.id')
            ->where('o.user_id', $userId)
            ->whereIn('o.status', [2, 3, 4])
            ->whereIn('p.project_group_id', $targetGroups)
            ->count();
        
        // PointsOrder、CoinOrder、InvestmentRecord、LoanApplication 这些表没有直接关联项目表
        // 根据业务逻辑，这些可能不属于产品组7,8,9,10,11，所以暂时不计算
        $pointsOrderCount = 0;
        $coinOrderCount = 0;
        $investmentCount = 0;
        $loanCount = 0;
        
        return ($orderCount + $dailyBonusCount + $tiyanCount + $tongxingCount + $pointsOrderCount + $coinOrderCount + $investmentCount + $loanCount) > 0;
    }
    
    /**
     * 计算民生钱包、稳盈钱包和稳盈钱包转入的总余额
     */
    private function calculateTwoWalletBalance($user)
    {
        $balance = bcadd($user['balance'], $user['butie'], 2);
        return bcadd($balance, $user['butie_lock'], 2);
    }
    

    
    /**
     * 幸福权益缴费三级返回
     * @param int $userId 缴费用户ID
     * @param float $paymentAmount 缴费金额
     * @param int $activationId 激活记录ID
     */
    private function distributeThreeLevelReward($userId, $paymentAmount, $activationId)
    {
        try {
            // 获取用户的上三级关系
            $relation = UserRelation::where('sub_user_id', $userId)->select();
            
            if (empty($relation)) {
                return;
            }
            
            // 获取缴费用户信息
            $user = User::where('id', $userId)->field('id,realname,phone')->find();
            
            // 三级返回比例配置（参考项目中其他地方的配置）
            $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$paymentAmount, 2);
                if($reward > 0){
                    User::changeInc($v['user_id'],$reward,'team_bonus_balance',117,$activationId,2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'XFQY');
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $userId,
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
        } catch (Exception $e) {
            \think\facade\Log::error('幸福权益缴费三级返回失败：' . $e->getMessage(), [
                'user_id' => $userId,
                'payment_amount' => $paymentAmount,
                'activation_id' => $activationId
            ]);
        }
    }
}

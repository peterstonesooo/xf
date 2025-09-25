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
                $equityRate = 1; // 有购买产品：1%
                $title = '幸福权益保障激活';
            } else {
                $equityRate = 1.5; // 无购买产品：1.5%
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
                $title = $equityRate==1?'幸福权益保障激活':'民生权益保障激活';
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
            return out(null, 10001, '无需重复激活');
            // 检查用户是否已激活
            $existingActivation = HappinessEquityActivation::getUserActivation($user['id']);
            if ($existingActivation) {
                return out(null, 10001, '您已激活幸福权益，无需重复激活');
            }
            
            // 检查用户是否有购买产品
            $hasPurchased = $this->checkUserHasPurchased($user['id']);
            
            // 根据是否有购买产品确定权益保障比例
            if ($hasPurchased) {
                $equityRate = 1; // 有购买产品：1%
            } else {
                $equityRate = 1.5; // 无购买产品：1.5%
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
                
                // 在事务外检查并发放团队奖励（避免事务冲突）
                // 获取用户的三级上级，分别检查他们的团队奖励
                // $upUserIds = User::getThreeUpUserId($user['id']);
                // foreach ($upUserIds as $upUserId) {
                //     if ($upUserId > 0) {
                //         self::checkAndDistributeTeamRewards($upUserId);
                //     }
                // }
                
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
     * 手动发放团队奖励（管理接口）
     */
    public function distributeTeamReward()
    {
        try {
            $req = $this->validate(request(), [
                'user_id|用户ID' => 'require|number'
            ]);
            
            $userId = $req['user_id'];
            
            // 调用公共方法检查并发放团队奖励
            $result = self::checkAndDistributeTeamRewards($userId);
            
            return out($result, $result['success'] ? 0 : 10001, $result['message']);
            
        } catch (Exception $e) {
            return out(null, 500, '发放团队奖励失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取团队统计数据（测试接口）
     */
    public function getTeamStats()
    {
        try {
            $user = $this->user;
            
            // 计算当前用户的团队统计数据
            $teamStats = self::calculateTeamStats($user['id']);
            
            // 获取上三级推荐人信息
            $relations = UserRelation::where('sub_user_id', $user['id'])->select();
            $parentStats = [];
            
            foreach ($relations as $relation) {
                $parentUserId = $relation['user_id'];
                $parentLevel = $relation['level'];
                
                $parentUser = User::where('id', $parentUserId)->field('id,realname,phone')->find();
                $parentTeamStats = self::calculateTeamStats($parentUserId);
                
                // 检查是否满足奖励条件
                $canReceiveReward = $parentTeamStats['team_real_name_count'] >= 100;
                $rewardStages = [];
                
                if ($canReceiveReward) {
                    $stages = [
                        1 => ['threshold' => 30, 'name' => '阶段1'],
                        2 => ['threshold' => 70, 'name' => '阶段2'],
                        3 => ['threshold' => 100, 'name' => '阶段3']
                    ];
                    
                    foreach ($stages as $stage => $config) {
                        if ($parentTeamStats['team_active_count'] >= $config['threshold']) {
                            $hasReceived = self::hasReceivedStageReward($parentUserId, $stage);
                            $rewardStages[] = [
                                'stage' => $stage,
                                'name' => $config['name'],
                                'threshold' => $config['threshold'],
                                'can_receive' => !$hasReceived,
                                'has_received' => $hasReceived
                            ];
                        }
                    }
                }
                
                $parentStats[] = [
                    'level' => $parentLevel,
                    'user_info' => $parentUser,
                    'team_stats' => $parentTeamStats,
                    'can_receive_reward' => $canReceiveReward,
                    'reward_stages' => $rewardStages
                ];
            }
            
            $data = [
                'current_user_stats' => $teamStats,
                'parent_users_stats' => $parentStats
            ];
            
            return out($data, 0, '获取成功');
            
        } catch (Exception $e) {
            return out(null, 500, '获取团队统计失败：' . $e->getMessage());
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
    
    /**
     * 检查并发放团队奖励（公共方法）
     * @param int $userId 用户ID
     * @return array 返回结果
     */
    public static function checkAndDistributeTeamRewards($userId)
    {
        try {
            // 计算用户的团队统计数据
            $teamStats = self::calculateTeamStats($userId);
            
            // 检查是否满足基本条件：团队实名人数 >= 100
            if ($teamStats['team_real_name_count'] < 100) {
                return [
                    'success' => false,
                    'message' => '团队实名人数不足100人，不满足奖励条件',
                    'team_stats' => $teamStats
                ];
            }
            
            $teamActiveCount = $teamStats['team_active_count'];
            
            // 检查各个阶段的奖励条件
            $stages = [
                1 => ['threshold' => 30, 'name' => '阶段1'],
                2 => ['threshold' => 70, 'name' => '阶段2'],
                3 => ['threshold' => 100, 'name' => '阶段3']
            ];
            
            $rewardResults = [];
            $totalRewardAmount = 0;
            
            foreach ($stages as $stage => $config) {
                if ($teamActiveCount >= $config['threshold']) {
                    // 检查团队成员是否已经发放过该阶段的奖励
                    $result = self::giveRewardToUserAndSubordinates($userId, $stage, $config['name'], $teamActiveCount);
                    if ($result['success']) {
                        $rewardResults[] = $result;
                        $totalRewardAmount += ($result['total_puhui_amount'] + $result['total_honor_amount']);
                    } else {
                        $rewardResults[] = [
                            'stage' => $stage,
                            'name' => $config['name'],
                            'success' => false,
                            'message' => $result['message']
                        ];
                    }
                }
            }
            
            if (empty($rewardResults)) {
                return [
                    'success' => false,
                    'message' => '没有满足条件的奖励阶段',
                    'team_stats' => $teamStats
                ];
            }
            
            return [
                'success' => true,
                'message' => '团队奖励发放成功',
                'team_stats' => $teamStats,
                'reward_results' => $rewardResults,
                'total_reward_amount' => $totalRewardAmount
            ];
            
        } catch (Exception $e) {
            \think\facade\Log::error('团队奖励检查失败：' . $e->getMessage(), [
                'user_id' => $userId
            ]);
            
            return [
                'success' => false,
                'message' => '团队奖励检查失败：' . $e->getMessage(),
                'team_stats' => []
            ];
        }
    }
    
    /**
     * 计算团队统计数据
     * @param int $userId 用户ID
     * @return array 团队统计数据
     */
    public static function calculateTeamStats($userId)
    {
        // 获取下三级团队总人数
        $teamTotalCount = UserRelation::where('user_id', $userId)
            ->whereIn('level', [1, 2, 3])
            ->count();
        
        // 获取下三级团队实名人数
        $teamRealNameCount = UserRelation::alias('ur')
            ->join('user u', 'u.id = ur.sub_user_id')
            ->where('ur.user_id', $userId)
            ->whereIn('ur.level', [1, 2, 3])
            ->where('u.shiming_status', 1)
            ->count();
        
        // 获取下三级团队幸福权益激活人数
        $teamActiveCount = UserRelation::alias('ur')
            ->join('happiness_equity_activation hea', 'hea.user_id = ur.sub_user_id')
            ->where('ur.user_id', $userId)
            ->whereIn('ur.level', [1, 2, 3])
            ->where('hea.status', 1)
            ->count();
        
        return [
            'team_total_count' => $teamTotalCount,
            'team_real_name_count' => $teamRealNameCount,
            'team_active_count' => $teamActiveCount
        ];
    }
    
    /**
     * 检查是否已经发放过该阶段的奖励
     * @param int $userId 用户ID
     * @param int $stage 阶段
     * @return bool
     */
    public static function hasReceivedStageReward($userId, $stage)
    {
        $existingReward = \app\model\TeamRewardRecord::where('sub_user_id', $userId)
            ->where('reward_level', $stage)
            ->where('remark', 'like', '%幸福权益团队奖励%')
            ->find();
            
        return !empty($existingReward);
    }
    
    /**
     * 给用户和他的下级发放奖励
     * @param int $userId 用户ID
     * @param int $stage 阶段
     * @param string $stageName 阶段名称
     * @param int $teamActiveCount 团队激活人数
     * @return array 返回结果
     */
    public static function giveRewardToUserAndSubordinates($userId, $stage, $stageName, $teamActiveCount)
    {
        try {
            // 获取奖励配置
            $rewardConfig = self::getStageRewardConfig($stage);
            
            if ($rewardConfig['puhui_amount'] <= 0 && $rewardConfig['tickets_amount'] <= 0) {
                return [
                    'success' => false,
                    'message' => '奖励配置为0',
                    'stage' => $stage,
                    'name' => $stageName
                ];
            }
            
            $rewardResults = [];
            $totalPuhuiAmount = 0;
            $totalTicketsAmount = 0;
            $totalHonorAmount = 0;
            $rewardedCount = 0;
            
            // 获取所有已激活的团队成员（包括用户本人）
            $teamMembers = self::getActiveTeamMembers($userId);
            
            foreach ($teamMembers as $member) {
                $memberId = $member['user_id'];
                $memberLevel = $member['level'];
                
                // 检查该成员是否已经领取过该阶段的奖励
                if (!self::hasReceivedStageReward($memberId, $stage)) {
                    // 所有团队成员获得相同的奖励金额
                    $memberPuhuiAmount = $rewardConfig['puhui_amount'];
                    $memberTicketsAmount = $rewardConfig['tickets_amount'];
                    
                    // 只有团长获得荣誉金奖励
                    $memberHonorAmount = $memberLevel == 0 ? $rewardConfig['leader_honor_amount'] : 0;
                    
                    if ($memberPuhuiAmount > 0 || $memberTicketsAmount > 0 || $memberHonorAmount > 0) {
                        // 给该成员发放奖励
                        $result = self::giveRewardToUser($userId, $memberId, $memberLevel, $stage, $stageName, $teamActiveCount, $memberPuhuiAmount, $memberTicketsAmount, $memberHonorAmount);
                        if ($result['success']) {
                            $rewardResults[] = $result;
                            $totalPuhuiAmount += $memberPuhuiAmount;
                            $totalTicketsAmount += $memberTicketsAmount;
                            $totalHonorAmount += $memberHonorAmount;
                            $rewardedCount++;
                        }
                    }
                }
            }
            
            if ($rewardedCount == 0) {
                return [
                    'success' => false,
                    'message' => '所有团队成员都已领取过该阶段奖励',
                    'stage' => $stage,
                    'name' => $stageName
                ];
            }
            
            return [
                'success' => true,
                'message' => "奖励发放成功，共发放给{$rewardedCount}人",
                'stage' => $stage,
                'name' => $stageName,
                'reward_results' => $rewardResults,
                'total_puhui_amount' => $totalPuhuiAmount,
                'total_tickets_amount' => $totalTicketsAmount,
                'total_honor_amount' => $totalHonorAmount,
                'rewarded_count' => $rewardedCount
            ];
            
        } catch (Exception $e) {
            \think\facade\Log::error('奖励发放失败：' . $e->getMessage(), [
                'user_id' => $userId,
                'stage' => $stage,
                'stage_name' => $stageName
            ]);
            
            return [
                'success' => false,
                'message' => '奖励发放失败：' . $e->getMessage(),
                'stage' => $stage,
                'name' => $stageName
            ];
        }
    }
    
    /**
     * 获取所有已激活的团队成员（包括用户本人）
     * @param int $userId 用户ID
     * @return array 团队成员列表
     */
    public static function getActiveTeamMembers($userId)
    {
        $members = [];
        
        // 添加用户本人（0级）
        $members[] = [
            'user_id' => $userId,
            'level' => 0
        ];
        
        // 获取用户的下三级已激活团队成员
        $subordinates = UserRelation::alias('ur')
            ->join('happiness_equity_activation hea', 'hea.user_id = ur.sub_user_id')
            ->where('ur.user_id', $userId)
            ->whereIn('ur.level', [1, 2, 3])
            ->where('hea.status', 1)
            ->field('ur.sub_user_id as user_id, ur.level')
            ->select()
            ->toArray();
        
        $members = array_merge($members, $subordinates);
        
        return $members;
    }
    
    /**
     * 给单个用户发放奖励
     * @param int $issuerId 发放者ID
     * @param int $receiverId 接收者ID
     * @param int $level 接收者级别
     * @param int $stage 阶段
     * @param string $stageName 阶段名称
     * @param int $teamActiveCount 团队激活人数
     * @param float $puhuiAmount 普惠钱包奖励金额
     * @param int $ticketsAmount 助力券奖励数量
     * @param float $honorAmount 荣誉金奖励金额
     * @return array 返回结果
     */
    public static function giveRewardToUser($issuerId, $receiverId, $level, $stage, $stageName, $teamActiveCount, $puhuiAmount, $ticketsAmount, $honorAmount)
    {
        Db::startTrans();
        try {
            $totalAmount = $puhuiAmount + $honorAmount;
            $rewardDetails = [];
            
            // 发放普惠钱包奖励
            if ($puhuiAmount > 0) {
                User::changeInc($receiverId, $puhuiAmount, 'puhui', 118, $issuerId, 13, 
                    "幸福权益团队奖励-{$stageName}(团队激活{$teamActiveCount}人)-普惠钱包", 0, 2, 'XFQY');
                $rewardDetails[] = "普惠钱包:{$puhuiAmount}元";
            }
            
            // 发放助力券奖励
            if ($ticketsAmount > 0) {
                User::changeInc($receiverId, $ticketsAmount, 'xingfu_tickets', 118, $issuerId, 12, 
                    "幸福权益团队奖励-{$stageName}(团队激活{$teamActiveCount}人)-助力券", 0, 2, 'XFQY');
                $rewardDetails[] = "助力券:{$ticketsAmount}张";
            }
            
            // 发放荣誉金奖励（仅团长）
            if ($honorAmount > 0) {
                User::changeInc($receiverId, $honorAmount, 'team_bonus_balance', 118, $issuerId, 2, 
                    "幸福权益团队奖励-{$stageName}(团队激活{$teamActiveCount}人)-荣誉金", 0, 2, 'XFQY');
                $rewardDetails[] = "荣誉金:{$honorAmount}元";
            }
            
            // 记录奖励发放记录
            \app\model\TeamRewardRecord::create([
                'user_id' => $issuerId,
                'sub_user_id' => $receiverId,
                'reward_level' => $stage,
                'reward_amount' => $totalAmount,
                'reward_type' => "幸福权益团队奖励-{$stageName}",
                'status' => 1,
                'remark' => "幸福权益团队奖励-{$stageName}(团队激活{$teamActiveCount}人)-{$level}级-" . implode(',', $rewardDetails)
            ]);
            
            Db::commit();
            
            \think\facade\Log::info('幸福权益团队奖励发放成功', [
                'issuer_id' => $issuerId,
                'receiver_id' => $receiverId,
                'level' => $level,
                'stage' => $stage,
                'stage_name' => $stageName,
                'team_active_count' => $teamActiveCount,
                'puhui_amount' => $puhuiAmount,
                'tickets_amount' => $ticketsAmount,
                'honor_amount' => $honorAmount,
                'total_amount' => $totalAmount
            ]);
            
            return [
                'success' => true,
                'message' => '奖励发放成功',
                'issuer_id' => $issuerId,
                'receiver_id' => $receiverId,
                'level' => $level,
                'stage' => $stage,
                'stage_name' => $stageName,
                'puhui_amount' => $puhuiAmount,
                'tickets_amount' => $ticketsAmount,
                'honor_amount' => $honorAmount,
                'total_amount' => $totalAmount
            ];
            
        } catch (Exception $e) {
            Db::rollback();
            \think\facade\Log::error('用户奖励发放失败：' . $e->getMessage(), [
                'issuer_id' => $issuerId,
                'receiver_id' => $receiverId,
                'stage' => $stage
            ]);
            
            return [
                'success' => false,
                'message' => '奖励发放失败：' . $e->getMessage(),
                'issuer_id' => $issuerId,
                'receiver_id' => $receiverId,
                'level' => $level,
                'stage' => $stage
            ];
        }
    }
    
    
    /**
     * 计算下级用户的奖励金额
     * @param float $baseAmount 基础奖励金额
     * @param int $level 下级用户级别
     * @return float 奖励金额
     */
    public static function calculateSubordinateReward($baseAmount, $level)
    {
        // 根据级别设置奖励比例
        $levelRatios = [
            1 => 0.5,  // 一级下级获得50%
            2 => 0.3,  // 二级下级获得30%
            3 => 0.2   // 三级下级获得20%
        ];
        
        $ratio = $levelRatios[$level] ?? 0;
        
        // 如果是助力券，需要取整
        if (is_int($baseAmount)) {
            return (int)round($baseAmount * $ratio);
        }
        
        return round($baseAmount * $ratio, 2);
    }
    
    /**
     * 获取阶段奖励配置
     * @param int $stage 阶段
     * @return array 奖励配置
     */
    public static function getStageRewardConfig($stage)
    {
        $rewardConfigs = [
            1 => [
                'puhui_amount' => 50,    // 普惠钱包奖励
                'tickets_amount' => 5,    // 助力券奖励
                'leader_honor_amount' => 0 // 团长荣誉金奖励
            ],
            2 => [
                'puhui_amount' => 100,    // 普惠钱包奖励
                'tickets_amount' => 10,    // 助力券奖励
                'leader_honor_amount' => 5000 // 团长荣誉金奖励
            ],
            3 => [
                'puhui_amount' => 200,    // 普惠钱包奖励
                'tickets_amount' => 0,    // 助力券奖励
                'leader_honor_amount' => 20000 // 团长荣誉金奖励
            ]
        ];
        
        return $rewardConfigs[$stage] ?? [
            'puhui_amount' => 0,
            'tickets_amount' => 0,
            'leader_honor_amount' => 0
        ];
    }
    
}

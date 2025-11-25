<?php

namespace app\model;

use think\Model;

class Project extends Model
{
    // JSON字段
    protected $json = ['huimin_days_return'];
    protected $jsonAssoc = true;

    /**
     * 发放黄金奖励（统一入口）
     *
     * @param int $userId               用户ID
     * @param float $quantity           黄金克数（支持小数）
     * @param string $logRemark         资金日志备注
     * @param array $options            额外配置项：
     *                                  - related_id(int)           关联ID（默认0）
     *                                  - gold_price(float)         金价（元/克，默认实时金价）
     *                                  - change_type(int)          User::changeInc type（默认52）
     *                                  - log_type(int)             资金流水类型（默认18）
     *                                  - change_admin_id(int)      管理员ID（默认0）
     *                                  - change_status(int)        资金流水状态（默认1）
     *                                  - change_order_prefix(string)资金流水单号前缀（默认'GOLD'）
     *                                  - change_is_delete(int)     资金流水是否删除标识（默认0）
     *                                  - gold_order_type(int)      黄金订单类型（默认GoldOrder::TYPE_REWARD）
     *                                  - gold_order_prefix(string) 黄金订单号前缀（默认'GOLDREWARD'）
     *                                  - gold_order_remark(string) 黄金订单备注（默认与logRemark一致）
     * @return GoldOrder|null
     */
    public static function rewardGold(int $userId, float $quantity, string $logRemark, array $options = []): ?GoldOrder
    {
        $rewardGold = round($quantity, 6);
        if ($userId <= 0 || $rewardGold <= 0) {
            return null;
        }

        $options = array_merge([
            'related_id' => 0,
            'gold_price' => 0.0,
            'change_type' => 52,
            'log_type' => 18,
            'change_admin_id' => 0,
            'change_status' => 1,
            'change_order_prefix' => 'GOLD',
            'change_is_delete' => 0,
            'gold_order_type' => GoldOrder::TYPE_REWARD,
            'gold_order_prefix' => 'GOLDREWARD',
            'gold_order_remark' => $logRemark,
        ], $options);

        $wallet = UserGoldWallet::getOrCreate($userId);
        $balanceBefore = round(floatval($wallet['gold_balance']), 6);
        $costPriceBefore = round(floatval($wallet['cost_price']), 6);

        $goldPrice = floatval($options['gold_price']);
        if ($goldPrice <= 0) {
            $goldPrice = self::getCurrentGoldPrice();
        }
        $goldPrice = round($goldPrice, 4);

        $amount = $goldPrice > 0 ? round($goldPrice * $rewardGold, 2) : 0;
        $balanceAfter = round($balanceBefore + $rewardGold, 6);

        if ($goldPrice > 0 && $balanceAfter > 0) {
            if ($balanceBefore <= 0) {
                $costPriceAfter = $goldPrice;
            } else {
                $costPriceAfter = round((($costPriceBefore * $balanceBefore) + ($goldPrice * $rewardGold)) / $balanceAfter, 6);
            }
        } else {
            $costPriceAfter = $costPriceBefore;
        }

        $orderNo = $options['gold_order_prefix'] . date('YmdHis') . str_pad($userId, 6, '0', STR_PAD_LEFT) . rand(1000, 9999);
        $goldOrder = GoldOrder::create([
            'order_no' => $orderNo,
            'user_id' => $userId,
            'type' => $options['gold_order_type'],
            'quantity' => $rewardGold,
            'price' => $goldPrice,
            'amount' => $amount,
            'fee' => 0,
            'fee_rate' => 0,
            'actual_amount' => $amount,
            'cost_price_before' => $costPriceBefore,
            'cost_price_after' => $costPriceAfter,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'profit' => 0,
            'status' => GoldOrder::STATUS_COMPLETED,
            'remark' => $options['gold_order_remark'],
        ]);

        User::changeInc(
            $userId,
            $rewardGold,
            'gold_wallet',
            $options['change_type'],
            $goldOrder->id,
            $options['log_type'],
            $logRemark,
            $options['change_admin_id'],
            $options['change_status'],
            $options['change_order_prefix'],
            $options['change_is_delete']
        );

        $wallet->gold_balance = $balanceAfter;
        if ($goldPrice > 0 && $balanceAfter > 0) {
            $wallet->cost_price = $costPriceAfter;
        }
        $wallet->total_buy_quantity = round(floatval($wallet['total_buy_quantity']) + $rewardGold, 6);
        if ($amount > 0) {
            $wallet->total_buy_amount = round(floatval($wallet['total_buy_amount']) + $amount, 2);
        }
        $wallet->save();

        return $goldOrder;
    }
    
    public static function order5(array $array)
    {
    }

    public function getStatusTextAttr($value, $data)
    {
        $map = config('map.project')['status_map'];
        return $map[$data['status']];
    }

    public function getIsRecommendTextAttr($value, $data)
    {
        $map = config('map.project')['is_recommend_map'];
        return $map[$data['is_recommend']];
    }
    
    //每日补贴比率
    public function getDailyBonusAttr($value, $data)
    {
        if (!empty($data['daily_bonus_ratio'])) {
            return round($data['daily_bonus_ratio'], 2);
        }

        return 0;
    }

    //被动收益
    public function getPassiveIncomeAttr($value, $data)
    {
        if (!empty($data['daily_bonus_ratio'])) {
            $bonus = $data['daily_bonus_ratio'];
            //$min = round($bonus*config('config.passive_income_days_conf')[1]/100, 2);
            $max = round($bonus*config('config.passive_income_days_conf')[77]/100, 2);
            return $max;
        }

        return 0;
    }

    public function getTotalBuyNumAttr($value, $data)
    {
        if (!empty($data['id']) || !empty($data['project_id'])) {
            $id = !empty($data['id']) ? $data['id'] : $data['project_id'];
            return Order::where('project_id', $id)->where('status', '>', 1)->sum('buy_num');
        }
        return 0;
    }

    public function getAllTotalBuyNumAttr($value, $data)
    {
        if (!empty($data['id']) || !empty($data['project_id'])) {
            $id = !empty($data['id']) ? $data['id'] : $data['project_id'];
            $buy_num = Order::where('project_id', $id)->where('status', '>', 1)->sum('buy_num');
            $buy_num = $data['sham_buy_num'] + $buy_num;
            return round($buy_num);
        }
        return 0;
    }

    public function getProgressAttr($value, $data)
    {
        if (!empty($data['id']) && !empty($data['total_num'])) {
            $buy_num = Order::where('project_id', $data['id'])->where('status', '>', 1)->sum('buy_num');
            $buy_num = $data['sham_buy_num'] + $buy_num;
            return round($buy_num/$data['total_num']*100, 2);
        }

        return 0;
    }

    public function getTotalAmountAttr($value, $data)
    {
        if (!empty($data['single_amount']) && !empty($data['total_num'])) {
            return round($data['single_amount']*$data['total_num'], 2);
        }

        return 0;
    }
    
    public function getDayAmountAttr($value, $data){
        if (!empty($data['sum_amount']) && !empty($data['period'])) {
            return round($data['sum_amount'] / $data['period'], 2);
        }
    }

    public function getSupportPayMethodsAttr($value)
    {
        return json_decode($value, true);
    }

    public function getSupportPayMethodsTextAttr($value, $data)
    {
        $arr = json_decode($data['support_pay_methods'], true);
        if (!empty($arr)) {
            $pay_text_arr = [];
            foreach ($arr as $v) {
                $pay_text_arr[] = config('map.order')['pay_method_map'][$v];
            }
            return implode(',', $pay_text_arr);
        }

        return '';
    }

    /**
     * 判断用户是否完成五福购买
     * 五福是指project_group_id为7,8,9,10,11的五个产品组
     * 每个组都需要至少购买一个当前启用中的产品才算完成五福购买
     * 需要同时检查mp_order和mp_order_daily_bonus两个表
     * @param int $userId 用户ID
     * @return bool 是否已完成五福购买
     */
    public static function checkWufuPurchase($userId)
    {
        // 五福产品组ID
        $wufuGroupIds = [7, 8, 9, 10, 11];
        
        foreach ($wufuGroupIds as $groupId) {
            $hasPurchased = false;
            
            // 检查mp_order表中的购买记录
            $orderPurchased = Order::alias('o')
                ->join('project p', 'o.project_id = p.id')
                ->where('o.user_id', $userId)
                ->where('p.project_group_id', $groupId)
                ->where('p.status', 1) // 产品状态为启用
                ->where('o.status', '>=', 2) // 订单状态已支付
                ->find();
                
            if (!empty($orderPurchased)) {
                $hasPurchased = true;
            }
            
            // 如果在mp_order表中没找到，再检查mp_order_daily_bonus表
            if (!$hasPurchased) {
                $dailyBonusPurchased = OrderDailyBonus::alias('o')
                    ->join('project p', 'o.project_id = p.id')
                    ->where('o.user_id', $userId)
                    ->where('p.project_group_id', $groupId)
                    ->where('p.status', 1) // 产品状态为启用
                    ->where('o.status', '>=', 2) // 订单状态已支付
                    ->find();
                    
                if (!empty($dailyBonusPurchased)) {
                    $hasPurchased = true;
                }
            }
            
            // 如果任何一个产品组在两个表中都没有购买记录，返回false
            if (!$hasPurchased) {
                return false;
            }
        }
        
        // 所有五个产品组都有购买记录
        return true;
    }

    /**
     * 检查用户是否完成了所有开放的五福临门板块申领
     * @param int $userId 用户ID
     * @return bool 是否已完成所有开放板块的申领
     */
    public static function checkAllOpenWufuCompleted($userId)
    {
        // 获取用户订单和日返订单
        $orders = Order::where('user_id', $userId)
                      ->where('status', 'in', [2, 4]) // 已支付或已完成状态
                      ->select();
        
        $dailyBonusOrders = OrderDailyBonus::where('user_id', $userId)
                                          ->where('status', 'in', [2, 4]) // 已支付或已完成状态
                                          ->select();

        // 获取各项目组的项目ID
        $projectGroups = [];
        $openGroups = []; // 开放的项目组
        
        for ($i = 7; $i <= 11; $i++) {
            // 获取普通项目（daily_bonus_ratio = 0）
            $normalProjects = self::where('project_group_id', $i)
                                ->where('status', 1)
                                ->where('daily_bonus_ratio', '=', 0)
                                ->column('id');
            
            // 获取日返项目（daily_bonus_ratio > 0）
            $dailyProjects = self::where('project_group_id', $i)
                               ->where('status', 1)
                               ->where('daily_bonus_ratio', '>', 0)
                               ->column('id');
            
            // 如果该组有开放的项目，则认为是开放的项目组
            if (!empty($normalProjects) || !empty($dailyProjects)) {
                $openGroups[] = $i;
                $projectGroups[$i]['normal'] = $normalProjects;
                $projectGroups[$i]['daily'] = $dailyProjects;
            }
        }

        // 如果没有开放的项目组，返回true
        if (empty($openGroups)) {
            return true;
        }

        // 获取用户订单的项目ID
        $orderProjectIds = $orders->column('project_id');
        $dailyOrderProjectIds = $dailyBonusOrders->column('project_id');

        // 检查用户是否完成了所有开放的项目组
        $completedGroups = [];

        foreach ($openGroups as $groupId) {
            $projects = $projectGroups[$groupId];
            
            // 检查普通项目是否全部完成
            $normalCompleted = !empty($projects['normal']) && 
                              count(array_intersect($projects['normal'], $orderProjectIds)) == count($projects['normal']);
            
            // 检查日返项目是否全部完成
            $dailyCompleted = !empty($projects['daily']) && 
                             count(array_intersect($projects['daily'], $dailyOrderProjectIds)) == count($projects['daily']);
            
            // 如果该组项目全部完成
            if ($normalCompleted && $dailyCompleted) {
                $completedGroups[] = $groupId;
            }
        }

        // 必须完成所有开放的项目组才能申请贷款
        return count($completedGroups) >= count($openGroups);
    }

    /**
     * 检查用户是否购买了至少一个开启中的五福产品
     * @param int $userId 用户ID
     * @return bool 是否购买了至少一个开启中的五福产品
     */
    public static function checkAnyOpenWufuCompleted($userId)
    {
        // 获取所有开启中的五福产品ID（project_group_id 在 [7,8,9,10,11] 范围内，status=1）
        $openWufuProjectIds = self::where('project_group_id', 'in', [7, 8, 9, 10, 11])
                                  ->where('status', 1)
                                  ->column('id');
        
        // 如果没有开启的五福产品，返回true（允许转入）
        if (empty($openWufuProjectIds)) {
            return true;
        }

        // 检查用户是否购买了其中任意一个产品（普通订单）
        $hasPurchased = Order::where('user_id', $userId)
                            ->where('project_id', 'in', $openWufuProjectIds)
                            ->where('status', 'in', [2, 4]) // 已支付或已完成状态
                            ->count() > 0;
        
        // 如果普通订单中没有，再检查日返订单
        if (!$hasPurchased) {
            $hasPurchased = OrderDailyBonus::where('user_id', $userId)
                                          ->where('project_id', 'in', $openWufuProjectIds)
                                          ->where('status', 'in', [2, 4]) // 已支付或已完成状态
                                          ->count() > 0;
        }

        return $hasPurchased;
    }

    /**
     * 获取用户完成产品组的次数统计
     * 返回每个产品组（7、8、9、10、11）用户完成的次数
     * 
     * @param int $userId 用户ID
     * @return array 返回格式：[7=>2, 8=>0, 9=>1, 10=>0, 11=>0]
     * 
     * 完成规则：
     * - 每个产品组包含普通项目（daily_bonus_ratio=0）和日返项目（daily_bonus_ratio>0）
     * - 用户必须购买该组的所有项目才算完成一次
     * - 根据buy_num计算：一个订单如果buy_num=3，则相当于完成了3次
     * - 如果用户购买了该组所有项目各2份（buy_num总和为2），则完成次数为2次
     */
    public static function getUserGroupCompletionCount($userId)
    {
        // 初始化结果数组
        $result = [
            7 => 0,
            8 => 0,
            9 => 0,
            10 => 0,
            11 => 0,
        ];
        
        // 遍历每个产品组
        foreach ($result as $groupId => &$count) {
            // 获取该组的普通项目ID（一次性分红）
            $normalProjects = self::where('project_group_id', $groupId)
                ->where('status', 1)
                ->where('daily_bonus_ratio', '=', 0)
                ->column('id');
            
            // 获取该组的日返项目ID（每日分红）
            $dailyProjects = self::where('project_group_id', $groupId)
                ->where('status', 1)
                ->where('daily_bonus_ratio', '>', 0)
                ->column('id');
            
            // 如果该组没有开放的项目，跳过
            if (empty($normalProjects) && empty($dailyProjects)) {
                continue;
            }
            //最小数量为4
            if(count($normalProjects) + count($dailyProjects) < 4) {
                continue;
            }
            
            // 初始化最小完成次数为无限大
            $minCompletionCount = PHP_INT_MAX;
            
            // 检查普通项目的完成次数
            if (!empty($normalProjects)) {
                foreach ($normalProjects as $projectId) {
                    // 统计用户购买该项目的数量总和（根据buy_num计算，一个订单可能购买多份）
                    $purchaseCount = Order::where('user_id', $userId)
                        ->where('project_id', $projectId)
                        ->where('created_at', '>=', '2025-10-27 00:00:00')
                        ->where('status', '>=', 2)
                        ->sum('buy_num');
                    
                    // 取最小值（木桶效应：完成次数由购买最少的项目决定）
                    $minCompletionCount = min($minCompletionCount, (int)$purchaseCount);
                }
            }
            
            // 检查日返项目的完成次数
            if (!empty($dailyProjects)) {
                foreach ($dailyProjects as $projectId) {
                    // 统计用户购买该项目的数量总和（根据buy_num计算，一个订单可能购买多份）
                    $purchaseCount = OrderDailyBonus::where('user_id', $userId)
                        ->where('project_id', $projectId)
                        ->where('created_at', '>=', '2025-10-27 00:00:00')
                        ->where('status', '>=', 2)
                        ->sum('buy_num');
                    
                    // 取最小值
                    $minCompletionCount = min($minCompletionCount, (int)$purchaseCount);
                }
            }
            
            // 如果最小值还是初始值，说明该组没有任何购买记录
            if ($minCompletionCount == PHP_INT_MAX) {
                $count = 0;
            } else {
                $count = $minCompletionCount;
            }
        }
        
        return $result;
    }


    /**
     * 检查用户完成情况并发放黄金奖励
     * 根据用户完成产品组的次数，发放相应克数的黄金
     * 记录到mp_gold_order表中，作为系统赠送的买入订单
     * 
     * @param int $userId 用户ID
     * @return array 返回发放结果
     */
    public static function checkUserGroupCompletionSendGold($userId)
    {
        // 获取用户完成情况
        $completionCount = self::getUserGroupCompletionCount($userId);
        
        // 获取黄金奖励配置（克数）
        $goldConfigs = \app\model\GoldApiConfig::where('key', 'in', [
            'complete_group_7',
            'complete_group_8',
            'complete_group_9',
            'complete_group_10',
            'complete_group_11',
            'complete_group_all'
        ])->column('val', 'key');
        
        // 获取当前金价
        $currentPrice = self::getCurrentGoldPrice();
        
        if ($currentPrice <= 0) {
            \think\facade\Log::error('获取金价失败，无法发放黄金奖励');
            return [
                'success' => false,
                'message' => '获取金价失败',
                'rewarded_count' => 0,
                'total_gold' => 0
            ];
        }
        
        $rewardedCount = 0;
        $totalGold = 0;
        $rewardDetails = [];
        $totalGoldForWallet = 0;  // 累积总黄金数（用于最后一次性更新钱包表）
        
        // 遍历每个产品组
        foreach ($completionCount as $groupId => $completedTimes) {
            if ($completedTimes <= 0) {
                continue; // 未完成，跳过
            }
            
            // 获取该组的奖励配置（克数）
            $configKey = 'complete_group_' . $groupId;
            $goldQuantityPerTime = floatval($goldConfigs[$configKey] ?? 0);
            
            if ($goldQuantityPerTime <= 0) {
                continue; // 未配置奖励，跳过
            }
            
            // 获取该组的所有项目ID（普通项目+日返项目）
            $normalProjects = self::where('project_group_id', $groupId)
                ->where('status', 1)
                ->where('daily_bonus_ratio', '=', 0)
                ->column('id');
            
            $dailyProjects = self::where('project_group_id', $groupId)
                ->where('status', 1)
                ->where('daily_bonus_ratio', '>', 0)
                ->column('id');
            
            // 合并所有项目ID并排序
            $allProjectIds = array_merge($normalProjects, $dailyProjects);
            sort($allProjectIds);
            
            // 项目ID组合（用逗号拼接）
            $projectIdsStr = implode(',', $allProjectIds);
            
            // 检查已发放次数（通过mp_gold_order表的remark字段识别项目组合）
            $rewardedTimes = \app\model\GoldOrder::where('user_id', $userId)
                ->where('type', 3) // type=3表示系统奖励
                ->where('remark', $projectIdsStr)
                ->count();
            
            // 计算需要发放的次数
            $needRewardTimes = $completedTimes - $rewardedTimes;
            
            if ($needRewardTimes <= 0) {
                continue; // 已全部发放
            }
            
            // 发放奖励
            for ($i = 1; $i <= $needRewardTimes; $i++) {
                $completionIndex = $rewardedTimes + $i;
                
                try {
                    // 计算黄金价值
                    $goldValue = $goldQuantityPerTime * $currentPrice;
                    
                    // 生成订单号
                    $orderNo = 'GOLDREWARD' . date('YmdHis') . str_pad($userId, 6, '0', STR_PAD_LEFT) . rand(1000, 9999);
                    
                    // 创建黄金订单记录（type=3表示系统奖励，remark存储项目ID组合）
                    $goldOrder = \app\model\GoldOrder::create([
                        'order_no' => $orderNo,
                        'user_id' => $userId,
                        'type' => 3, // 3-系统奖励
                        'quantity' => $goldQuantityPerTime,
                        'price' => $currentPrice,
                        'amount' => $goldValue,
                        'fee' => 0,
                        'fee_rate' => 0,
                        'actual_amount' => $goldValue,
                        'cost_price_before' => 0,
                        'cost_price_after' => 0,
                        'balance_before' => 0,
                        'balance_after' => 0,
                        'profit' => 0,
                        'status' => 1, // 已完成
                        'remark' => $projectIdsStr, // 存储项目ID组合，如："101,102,103"
                    ]);
                    
                    // 使用 User::changeInc 增加用户黄金余额并记录日志
                    User::changeInc(
                        $userId,
                        $goldQuantityPerTime,  // 增加的黄金克数
                        'gold_wallet',         // 字段名
                        125,                   // type=125（单组黄金奖励）
                        $goldOrder->id,        // 关联黄金订单ID
                        18,                    // log_type=18（黄金收入）
                        "国家黄金储备",
                        0,                     // 系统操作
                        1,                     // status=1（已完成）
                        'GOLD',                // 订单前缀
                        0                      // 不删除
                    );
                    
                    $rewardedCount++;
                    $totalGold += $goldQuantityPerTime;
                    $totalGoldForWallet += $goldQuantityPerTime;  // 累积到总数
                    
                    $rewardDetails[] = [
                        'group_id' => $groupId,
                        'completion_index' => $completionIndex,
                        'gold_quantity' => $goldQuantityPerTime,
                        'gold_value' => $goldValue,
                    ];
                    
                    \think\facade\Log::info("用户{$userId}完成产品组{$groupId}第{$completionIndex}次（项目ID：{$projectIdsStr}），发放黄金{$goldQuantityPerTime}克，价值{$goldValue}元");
                    
                } catch (\Exception $e) {
                    \think\facade\Log::error("发放黄金奖励失败：用户{$userId}，产品组{$groupId}，错误：" . $e->getMessage());
                }
            }
        }
        
        // 处理complete_group_all（完成所有产品组的额外奖励）
        $allGroupMinCompletion = min($completionCount); // 取所有组的最小完成次数
        
        if ($allGroupMinCompletion > 0) {
            // 所有组都至少完成了1次以上
            $goldQuantityPerTime = floatval($goldConfigs['complete_group_all'] ?? 0);
            
            if ($goldQuantityPerTime > 0) {
                // 获取所有产品组的项目ID组合，用于remark标识
                $allGroupProjectIds = [];
                foreach ([7, 8, 9, 10, 11] as $gid) {
                    $normalProjects = self::where('project_group_id', $gid)
                        ->where('status', 1)
                        ->where('daily_bonus_ratio', '=', 0)
                        ->column('id');
                    
                    $dailyProjects = self::where('project_group_id', $gid)
                        ->where('status', 1)
                        ->where('daily_bonus_ratio', '>', 0)
                        ->column('id');
                    
                    $groupIds = array_merge($normalProjects, $dailyProjects);
                    sort($groupIds);
                    $allGroupProjectIds[] = implode(',', $groupIds);
                }
                
                // 用竖线分隔各组，格式如："101,102,103|201,202|301,302,303|401,402|501,502"
                $allGroupsRemark = implode('|', $allGroupProjectIds);
                
                // 检查已发放complete_group_all的次数
                $allRewardedTimes = \app\model\GoldOrder::where('user_id', $userId)
                    ->where('type', 3)
                    ->where('remark', $allGroupsRemark)
                    ->count();
                
                // 需要发放的次数 = 所有组的最小完成次数 - 已发放次数
                $needRewardTimes = $allGroupMinCompletion - $allRewardedTimes;
                
                if ($needRewardTimes > 0) {
                    // 发放complete_group_all奖励
                    for ($i = 1; $i <= $needRewardTimes; $i++) {
                        $completionIndex = $allRewardedTimes + $i;
                        
                        try {
                            // 计算黄金价值
                            $goldValue = $goldQuantityPerTime * $currentPrice;
                            
                            // 生成订单号
                            $orderNo = 'GOLDREWARD' . date('YmdHis') . str_pad($userId, 6, '0', STR_PAD_LEFT) . rand(1000, 9999);
                            
                            // 创建黄金订单记录（type=3表示系统奖励）
                            $goldOrder = \app\model\GoldOrder::create([
                                'order_no' => $orderNo,
                                'user_id' => $userId,
                                'type' => 3,
                                'quantity' => $goldQuantityPerTime,
                                'price' => $currentPrice,
                                'amount' => $goldValue,
                                'fee' => 0,
                                'fee_rate' => 0,
                                'actual_amount' => $goldValue,
                                'cost_price_before' => 0,
                                'cost_price_after' => 0,
                                'balance_before' => 0,
                                'balance_after' => 0,
                                'profit' => 0,
                                'status' => 1,
                                'remark' => $allGroupsRemark, // 存储所有组的项目ID，格式："101,102|201,202|301,302|401,402|501,502"
                            ]);
                            
                            // 使用 User::changeInc 增加用户黄金余额并记录日志
                            User::changeInc(
                                $userId,
                                $goldQuantityPerTime,  // 增加的黄金克数
                                'gold_wallet',         // 字段名
                                125,                   // type=125（完成全部产品组黄金奖励）
                                $goldOrder->id,        // 关联黄金订单ID
                                18,                    // log_type=18（黄金收入）
                                "国家黄金储备",
                                0,                     // 系统操作
                                1,                     // status=1（已完成）
                                'GOLD',                // 订单前缀
                                0                      // 不删除
                            );
                            
                            $rewardedCount++;
                            $totalGold += $goldQuantityPerTime;
                            $totalGoldForWallet += $goldQuantityPerTime;  // 累积到总数
                            
                            $rewardDetails[] = [
                                'group_id' => 'all',
                                'completion_index' => $completionIndex,
                                'gold_quantity' => $goldQuantityPerTime,
                                'gold_value' => $goldValue,
                            ];
                            
                            \think\facade\Log::info("用户{$userId}完成全部产品组第{$completionIndex}轮（项目组合：{$allGroupsRemark}），发放额外黄金{$goldQuantityPerTime}克，价值{$goldValue}元");
                            
                        } catch (\Exception $e) {
                            \think\facade\Log::error("发放complete_group_all奖励失败：用户{$userId}，错误：" . $e->getMessage());
                        }
                    }
                }
            }
        }
        
        // 最后统一更新钱包表（一次性更新所有黄金）
        if ($totalGoldForWallet > 0) {
            try {
                \app\model\UserGoldWallet::addRewardGold($userId, $totalGoldForWallet, $currentPrice);
                \think\facade\Log::info("用户{$userId}钱包表统一更新：增加{$totalGoldForWallet}克黄金");
            } catch (\Exception $e) {
                \think\facade\Log::error("更新钱包表失败：用户{$userId}，黄金{$totalGoldForWallet}克，错误：" . $e->getMessage());
            }
        }
        
        return [
            'success' => true,
            'message' => $rewardedCount > 0 ? "成功发放{$rewardedCount}次奖励，共{$totalGold}克黄金" : '无需发放奖励',
            'rewarded_count' => $rewardedCount,
            'total_gold' => $totalGold,
            'reward_details' => $rewardDetails,
        ];
    }
    
    /**
     * 获取当前金价（从K线表）
     * @return float
     */
    private static function getCurrentGoldPrice()
    {
        $kline = \app\model\GoldKline::where([
            'period' => '1min',
            'price_type' => 'CNY'
        ])->order('start_time', 'desc')->find();
        if(!$kline){
            $kline = \app\model\GoldKline::where([
                'period' => '1day',
                'price_type' => 'CNY'
            ])->order('start_time', 'desc')->find();
        }
        
        return $kline ? floatval($kline->close_price) : 0;
    }
    
    /**
     * 获取用户可提现余额
     * 根据参与的五福产品组解锁提现额度
     * 
     * @param int $userId 用户ID
     * @return float 可提现余额
     */
    public static function getUserCanWithdrawBalance($userId)
    {
        $user = User::where('id', $userId)->find();
        
        // 五福产品组解锁额度配置（单位：元）
        $unlockAmounts = [
            7 => 300000,  // 健康福：30万
            8 => 800000,  // 智享福：80万
            9 => 500000,  // 就业福：50万
            10 => 1500000, // 兴农福：150万
            11 => 2000000, // 惠享福：200万
        ];
        
        // 计算解锁额度（每个组只能算一次）
        $unlockBalance = 0;
        $wufuGroupIds = [7, 8, 9, 10, 11];
        
        foreach ($wufuGroupIds as $groupId) {
            $hasParticipated = false;
            
            // 检查mp_order表中的购买记录（申领或预定）
            $orderPurchased = Order::alias('o')
                ->join('project p', 'o.project_id = p.id')
                ->where('o.user_id', $userId)
                ->where('p.project_group_id', $groupId)
                ->where('p.status', 1) // 产品状态为启用
                // ->where('o.status', '>=', 2) // 订单状态已支付
                ->find();
                
            if (!empty($orderPurchased)) {
                $hasParticipated = true;
            }
            
            // 如果在mp_order表中没找到，再检查mp_order_daily_bonus表
            if (!$hasParticipated) {
                $dailyBonusPurchased = OrderDailyBonus::alias('o')
                    ->join('project p', 'o.project_id = p.id')
                    ->where('o.user_id', $userId)
                    ->where('p.project_group_id', $groupId)
                    ->where('p.status', 1) // 产品状态为启用
                    // ->where('o.status', '>=', 2) // 订单状态已支付
                    ->find();
                    
                if (!empty($dailyBonusPurchased)) {
                    $hasParticipated = true;
                }
            }
            
            // 如果参与了该组，加上对应的解锁额度
            if ($hasParticipated && isset($unlockAmounts[$groupId])) {
                $unlockBalance += $unlockAmounts[$groupId];
            }
        }
        
        // 可提现余额 = 总资产 - 锁定余额 + 解锁额度
        return $unlockBalance;
    }

    /**
     * 获取该用户当天邀请的实名认证用户数
     * @param int $userId 用户ID
     * @return int 当天邀请的实名认证用户数
     */
    public static function getTodayInvitedRealnameCount($userId)
    {
        if (empty($userId)) {
            return 0;
        }
        
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        
        return User::where('up_user_id', $userId)
            ->where('shiming_status', 1)
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->count();
    }

}

<?php

namespace app\model;

use think\Model;

class Project extends Model
{
    // JSON字段
    protected $json = ['huimin_days_return'];
    protected $jsonAssoc = true;
    
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
}

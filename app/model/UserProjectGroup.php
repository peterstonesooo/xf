<?php

namespace app\model;

use think\Model;

class UserProjectGroup extends Model
{
    protected $name = 'user_project_group';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'user_id'     => 'int',
        'group_id'    => 'int',
        'group_name'  => 'string',
        'completed_projects' => 'text', // JSON格式存储完成的项目ID
        'completed_count' => 'int',
        'total_count' => 'int',
        'completed_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 检查用户是否完成指定产品组
     */
    public static function isGroupCompleted($user_id, $group_id)
    {
        // 获取该组的所有项目
        $groupProjects = Project::where('project_group_id', $group_id)
                               ->where('status', 1)
                               ->column('id');
        
        if (empty($groupProjects)) {
            return false;
        }

        $projectCombination = json_encode(array_values($groupProjects));
        
        return self::where('user_id', $user_id)
                   ->where('group_id', $group_id)
                   ->where('completed_projects', $projectCombination)
                   ->count() > 0;
    }

    /**
     * 获取用户完成的产品组数量
     */
    public static function getUserCompletedGroups($user_id)
    {
        // 直接统计该用户在表中的记录数量，因为只记录已完成的
        return self::where('user_id', $user_id)->count();
    }

    /**
     * 检查并更新用户的产品组完成状态
     */
    public static function checkAndUpdateUserGroups($user_id)
    {
        $user = User::find($user_id);
        if (!$user) {
            return false;
        }

        // 获取用户的所有订单
        $orders = Order::where('user_id', $user_id)
                      ->where('status', 'in', [2, 4])
                      ->select();
        
        $dailyBonusOrders = OrderDailyBonus::where('user_id', $user_id)
                                          ->where('status', 'in', [2, 4])
                                          ->select();

        // 合并所有项目ID
        $orderProjectIds = $orders ? $orders->column('project_id') : [];
        $dailyBonusProjectIds = $dailyBonusOrders ? $dailyBonusOrders->column('project_id') : [];
        $projectIds = array_merge($orderProjectIds, $dailyBonusProjectIds);

        // 检查每个产品组
        $groups = [
            7 => '五福临门板块1',
            8 => '五福临门板块2', 
            9 => '五福临门板块3',
            10 => '五福临门板块4',
            11 => '五福临门板块5'
        ];

        foreach ($groups as $group_id => $group_name) {
            // 获取该组的所有项目
            $groupProjects = Project::where('project_group_id', $group_id)
                                   ->where('status', 1)
                                   ->column('id');
            
            if (empty($groupProjects)) {
                continue;
            }

            // 检查用户是否完成了该组的所有项目
            $completedProjects = array_intersect($projectIds, $groupProjects);
            $completedCount = count($completedProjects);
            $totalCount = count($groupProjects);

            // 检查是否已经记录过这个特定的项目组合
            $projectCombination = json_encode(array_values($groupProjects)); // 排序后的项目ID组合
            $existingRecord = self::where('user_id', $user_id)
                                 ->where('group_id', $group_id)
                                 ->where('completed_projects', $projectCombination)
                                 ->find();

            if ($completedCount >= $totalCount) {
                // 完成该组，只记录已完成的
                if (!$existingRecord) {
                    // 创建新记录
                    self::create([
                        'user_id' => $user_id,
                        'group_id' => $group_id,
                        'group_name' => $group_name,
                        'completed_projects' => $projectCombination,
                        'completed_count' => $completedCount,
                        'total_count' => $totalCount,
                        'completed_at' => date('Y-m-d H:i:s'),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                // 如果已存在记录且状态为已完成，则不需要更新
            }
            // 未完成的不记录
        }

        return true;
    }
}

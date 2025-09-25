<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\User;
use app\model\UserRelation;
use app\model\HappinessEquityActivation;
use app\model\TeamRewardRecord;
use think\facade\View;
use think\facade\Db;
use Exception;

class RewardController extends AuthController
{
    /**
     * 奖励列表页面
     */
    public function rewardList()
    {
        $req = request()->param();
        $userId = $req['user_id'] ?? 0;
        
        if (!$userId) {
            return $this->error('用户ID不能为空');
        }
        
        // 获取用户信息
        $user = User::find($userId);
        if (!$user) {
            return $this->error('用户不存在');
        }
        
        // 获取用户的下三级团队信息
        $teamData = $this->getTeamData($userId);
        
        // 获取已发放的奖励记录
        $rewardRecords = TeamRewardRecord::getIssuerRewards($userId);
        
        View::assign('user', $user);
        View::assign('teamData', $teamData);
        View::assign('rewardRecords', $rewardRecords);
        View::assign('req', $req);
        
        return View::fetch('reward/reward_list');
    }
    
    /**
     * 发放奖励 - 根据选择的阶段发放奖励
     */
    public function distributeReward()
    {
        $req = request()->param();
        $this->validate($req, [
            'user_id' => 'require|number',
            'reward_stage' => 'require|in:1,2,3'
        ]);
        
        try {
            $userId = $req['user_id'];
            $rewardStage = $req['reward_stage'];
            
            // 获取用户信息
            $user = User::find($userId);
            if (!$user) {
                return out(null, 10001, '用户不存在');
            }
            
            // 获取所有激活的下级用户（所有阶段都发放给所有激活成员）
            $teamMembers = $this->getActiveTeamMembers($userId);
            
            if (empty($teamMembers)) {
                return out(null, 10001, '没有找到符合条件的团队成员');
            }
            
            Db::startTrans();
            
            $successCount = 0;
            $totalAmount = 0;
            $rewardRecords = [];
            
            // 定义奖励金额
            $rewardAmounts = [
                1 => 50.00,  // 阶段一奖励：50元
                2 => 100.00, // 阶段二奖励：100元
                3 => 200.00  // 阶段三奖励：200元
            ];
            
            $rewardAmount = $rewardAmounts[$rewardStage];
            
            foreach ($teamMembers as $member) {
                $subUserId = $member['sub_user_id'];
                
                // 检查是否已经发放过该阶段的奖励
                if (TeamRewardRecord::hasReceivedReward($subUserId, $rewardStage) !== null) {
                    continue; // 跳过已发放的
                }
                
                // 发放奖励到用户钱包
                $result = User::changeInc(
                    $subUserId, 
                    $rewardAmount, 
                    'puhui', 
                    118, // 奖励类型ID
                    $userId, 
                    13, // 钱包类型
                    '团队' . $this->getRewardTypeText($rewardStage) . '奖励', 
                    0, 
                    1, 
                    'TD'
                );
                
                if ($result) {
                    // 记录奖励发放
                    $rewardRecord = new TeamRewardRecord();
                    $rewardRecord->user_id = $userId;
                    $rewardRecord->sub_user_id = $subUserId;
                    $rewardRecord->reward_level = $rewardStage;
                    $rewardRecord->reward_amount = $rewardAmount;
                    $rewardRecord->reward_type = $this->getRewardTypeText($rewardStage) . '奖励';
                    $rewardRecord->status = 1;
                    $rewardRecord->remark = '发放' . $this->getRewardTypeText($rewardStage) . '团队奖励';
                    $rewardRecord->save();
                    
                    $successCount++;
                    $totalAmount += $rewardAmount;
                    $rewardRecords[] = [
                        'sub_user_id' => $subUserId,
                        'level' => $rewardStage,
                        'amount' => $rewardAmount
                    ];
                }
            }
            
            Db::commit();
            
            return out([
                'success_count' => $successCount,
                'total_amount' => $totalAmount,
                'reward_records' => $rewardRecords
            ], 200, '奖励发放成功，共发放' . $successCount . '人，总金额' . $totalAmount . '元');
            
        } catch (Exception $e) {
            Db::rollback();
            return out(null, 10001, '奖励发放失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取激活的团队成员
     */
    private function getActiveTeamMembers($userId)
    {
        return UserRelation::alias('ur')
            ->join('happiness_equity_activation hea', 'hea.user_id = ur.sub_user_id')
            ->where('ur.user_id', $userId)
            ->whereIn('ur.level', [1, 2, 3])
            ->where('hea.status', 1) // 只获取激活的用户
            ->field('ur.sub_user_id, ur.level')
            ->select()
            ->toArray();
    }
    
    /**
     * 获取指定级别的激活团队成员
     */
    private function getActiveTeamMembersByLevel($userId, $level)
    {
        return UserRelation::alias('ur')
            ->join('happiness_equity_activation hea', 'hea.user_id = ur.sub_user_id')
            ->where('ur.user_id', $userId)
            ->where('ur.level', $level)
            ->where('hea.status', 1) // 只获取激活的用户
            ->field('ur.sub_user_id, ur.level')
            ->select()
            ->toArray();
    }
    
    /**
     * 获取团队数据
     */
    private function getTeamData($userId)
    {
        $data = [];
        
        // 获取下三级团队总人数
        $data['team_total_count'] = UserRelation::where('user_id', $userId)
            ->whereIn('level', [1, 2, 3])
            ->count();
        
        // 获取下三级团队实名人数
        $data['team_real_name_count'] = UserRelation::alias('ur')
            ->join('user u', 'u.id = ur.sub_user_id')
            ->where('ur.user_id', $userId)
            ->whereIn('ur.level', [1, 2, 3])
            ->where('u.shiming_status', 1)
            ->count();
        
        // 获取下三级团队激活人数
        $data['team_active_count'] = UserRelation::alias('ur')
            ->join('happiness_equity_activation hea', 'hea.user_id = ur.sub_user_id')
            ->where('ur.user_id', $userId)
            ->whereIn('ur.level', [1, 2, 3])
            ->where('hea.status', 1)
            ->count();
        
        // 获取各级别详细数据
        for ($level = 1; $level <= 3; $level++) {
            $levelData = UserRelation::alias('ur')
                ->join('user u', 'u.id = ur.sub_user_id')
                ->leftJoin('happiness_equity_activation hea', 'hea.user_id = ur.sub_user_id AND hea.status = 1')
                ->where('ur.user_id', $userId)
                ->where('ur.level', $level)
                ->field('ur.sub_user_id, u.phone, u.realname, u.shiming_status, hea.id as activation_id')
                ->select()
                ->toArray();
            
            $data['level_' . $level] = [
                'total_count' => count($levelData),
                'real_name_count' => count(array_filter($levelData, function($item) { return $item['shiming_status'] == 1; })),
                'active_count' => count(array_filter($levelData, function($item) { return !empty($item['activation_id']); })),
                'members' => $levelData
            ];
        }
        
        return $data;
    }
    
    /**
     * 获取奖励类型文本
     */
    private function getRewardTypeText($type)
    {
        $map = [
            1 => '阶段一',
            2 => '阶段二',
            3 => '阶段三'
        ];
        
        return $map[$type] ?? '未知';
    }
}

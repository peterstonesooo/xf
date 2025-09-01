<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\model\HappinessEquityActivation;
use app\model\User;
use app\model\UserRelation;
use app\model\RelationshipRewardLog;
use app\model\UserBalanceLog;
use think\facade\Db;
use Exception;

class RepairHappinessEquityReward extends Command
{
    protected function configure()
    {
        $this->setName('repair:happiness_equity_reward')
             ->setDescription('补返幸福权益缴费三级返回');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始执行幸福权益缴费三级返回补返任务...');
        
        try {
            // 获取所有已激活幸福权益的用户
            $activations = HappinessEquityActivation::where('status', 1)->select();
            
            $totalCount = count($activations);
            $successCount = 0;
            $errorCount = 0;
            
            $output->writeln("共找到 {$totalCount} 个已激活幸福权益的用户");
            
            foreach ($activations as $activation) {
                try {
                    $result = $this->repairUserReward($activation, $output);
                    if ($result) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $output->writeln("用户ID {$activation['user_id']} 补返失败: " . $e->getMessage());
                }
            }
            
            $output->writeln("补返任务完成！");
            $output->writeln("成功: {$successCount} 个");
            $output->writeln("失败: {$errorCount} 个");
            
        } catch (Exception $e) {
            $output->writeln("任务执行失败: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    /**
     * 为单个用户补返三级返回
     */
    private function repairUserReward($activation, $output)
    {
        $userId = $activation['user_id'];
        $paymentAmount = $activation['payment_amount'];
        $activationId = $activation['id'];
        
        // 检查是否已经发放过三级返回
        $existingReward = UserBalanceLog::where('user_id', '>', 0)
            ->where('type', 116)
            ->where('remark', 'like', '团队奖励%级%')
            ->where('order_id', $activationId)
            ->find();
            
        if ($existingReward) {
            $output->writeln("用户ID {$userId} 已发放过三级返回，跳过");
            return true;
        }
        
        // 获取用户的上三级关系
        $relation = UserRelation::where('sub_user_id', $userId)->select();
        
        if (empty($relation)) {
            $output->writeln("用户ID {$userId} 没有上级关系，跳过");
            return true;
        }
        
        // 获取缴费用户信息
        $user = User::where('id', $userId)->field('id,realname,phone')->find();
        if (!$user) {
            $output->writeln("用户ID {$userId} 不存在，跳过");
            return false;
        }
        
        // 三级返回比例配置
        $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
        
        $rewardCount = 0;
        
        Db::startTrans();
        try {
            foreach ($relation as $v) {
                $reward = round(dbconfig($map[$v['level']])/100*$paymentAmount, 2);
                if($reward > 0){
                    // 给上级用户发放奖励到团队奖励钱包
                    User::changeInc(
                        $v['user_id'],
                        $reward,
                        'team_bonus_balance',
                        116,
                        $activationId,
                        2,
                        '团队奖励'.$v['level'].'级'.$user['realname'],
                        0,
                        2,
                        'XFQY'
                    );
                    
                    // 记录关系奖励日志
                    RelationshipRewardLog::insert([
                        'uid' => $v['user_id'],
                        'reward' => $reward,
                        'son' => $userId,
                        'son_lay' => $v['level'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $rewardCount++;
                    
                    $output->writeln("用户ID {$userId} -> 上级ID {$v['user_id']} ({$v['level']}级) 补返 {$reward} 元");
                }
            }
            
            Db::commit();
            
            if ($rewardCount > 0) {
                $output->writeln("用户ID {$userId} 补返完成，共发放 {$rewardCount} 笔奖励");
            } else {
                $output->writeln("用户ID {$userId} 无需补返");
            }
            
            return true;
            
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}

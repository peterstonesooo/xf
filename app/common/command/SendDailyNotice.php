<?php

namespace app\common\command;

use app\model\NoticeMessage;
use app\model\User;
use app\model\Project;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

class SendDailyNotice extends Command
{
    protected function configure()
    {
        $this->setName('sendDailyNotice')
            ->setDescription('发放黄金-国家黄金储备')
            ->addArgument('phone', \think\console\input\Argument::OPTIONAL, '用户手机号（可选，不传则处理所有用户）');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $phone = $input->getArgument('phone');
            
            if ($phone) {
                // 处理单个用户
                $this->processSingleUser($phone, $output);
            } else {
                // 处理所有用户
                // $this->processAllUsers($output);
            }
            
            $output->writeln('执行完成');
            
        } catch (\Exception $e) {
            $output->writeln('执行失败：' . $e->getMessage());
            Log::error('SendDailyNotice执行失败：' . $e->getMessage());
        }
    }
    
    /**
     * 处理单个用户
     */
    private function processSingleUser($phone, Output $output)
    {
        $output->writeln("正在处理手机号：{$phone}");
        
        // 根据手机号查找用户
        $user = User::where('phone', $phone)->find();
        
        if (!$user) {
            $output->writeln("错误：手机号 {$phone} 对应的用户不存在");
            Log::warning("SendDailyNotice - 用户不存在：{$phone}");
            return;
        }
        
        $output->writeln("找到用户：ID={$user['id']}, 姓名={$user['realname']}");
        
        // 调用发放黄金方法
        $result = Project::checkUserGroupCompletionSendGold($user['id']);
        
        if ($result['success']) {
            $output->writeln("✓ 成功：发放 {$result['rewarded_count']} 次奖励，共 {$result['total_gold']} 克黄金");
            Log::info("SendDailyNotice - 用户 {$phone} (ID:{$user['id']}) 发放黄金成功", $result);
        } else {
            $output->writeln("× 失败：{$result['message']}");
            Log::error("SendDailyNotice - 用户 {$phone} (ID:{$user['id']}) 发放黄金失败：{$result['message']}");
        }
    }
    
    /**
     * 处理所有用户
     */
    private function processAllUsers(Output $output)
    {
        $output->writeln("正在处理所有用户...");
        
        // 获取所有用户（可以添加条件，比如只处理已实名的用户）
        $users = User::where('status', 1)
            // ->where('shiming_status', 1) // 可选：只处理已实名用户
            ->select();
        
        $totalUsers = count($users);
        $successCount = 0;
        $failCount = 0;
        $totalGold = 0;
        
        $output->writeln("找到 {$totalUsers} 个用户");
        
        foreach ($users as $index => $user) {
            $output->write("处理用户 " . ($index + 1) . "/{$totalUsers} (ID:{$user['id']}, {$user['phone']})...");
            
            try {
                $result = Project::checkUserGroupCompletionSendGold($user['id']);
                
                if ($result['success']) {
                    $successCount++;
                    $totalGold += $result['total_gold'];
                    
                    if ($result['rewarded_count'] > 0) {
                        $output->writeln(" ✓ 发放 {$result['rewarded_count']} 次，共 {$result['total_gold']} 克");
                    } else {
                        $output->writeln(" - 无需发放");
                    }
                } else {
                    $failCount++;
                    $output->writeln(" × 失败：{$result['message']}");
                }
                
            } catch (\Exception $e) {
                $failCount++;
                $output->writeln(" × 异常：{$e->getMessage()}");
                Log::error("SendDailyNotice - 处理用户 {$user['id']} 异常：" . $e->getMessage());
            }
            
            // 避免过快，休眠100毫秒
            usleep(100000);
        }
        
        $output->writeln("\n====== 处理完成 ======");
        $output->writeln("总用户数：{$totalUsers}");
        $output->writeln("成功：{$successCount}");
        $output->writeln("失败：{$failCount}");
        $output->writeln("累计发放黄金：{$totalGold} 克");
        
        Log::info("SendDailyNotice批量处理完成", [
            'total' => $totalUsers,
            'success' => $successCount,
            'fail' => $failCount,
            'total_gold' => $totalGold
        ]);
    }
} 
<?php

namespace app\common\command;

use app\model\NoticeMessage;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

class SendDailyNotice extends Command
{
    protected function configure()
    {
        $this->setName('sendDailyNotice')
            ->setDescription('发送每日系统通知');
    }

    protected function execute(Input $input, Output $output)
    { 
        try {
            // 获取所有用户ID
            $userIds = User::where('status', 1)->column('id');
            
            if (empty($userIds)) {
                $output->writeln('没有找到有效用户');
                return;
            }

            // 发送系统通知
            $title = '每日提醒';
            $content = '亲爱的用户，请记得完成今日定投任务，保持投资连续性，获得稳定收益。';
            
            $result = NoticeMessage::sendSystemNotice($title, $content, $userIds);
            
            if ($result) {
                $output->writeln('消息发送成功，发送用户数：' . count($userIds));
            } else {
                $output->writeln('消息发送失败');
                Log::error('每日系统通知发送失败');
            }
        } catch (\Exception $e) {
            $output->writeln('执行出错：' . $e->getMessage());
            Log::error('每日系统通知发送异常：' . $e->getMessage());
        }
    }
} 
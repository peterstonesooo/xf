<?php

namespace app\common\command;

use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class MigrateWalletBalance extends Command
{
    protected function configure()
    {
        $this->setName('migrate:wallet-balance')
            ->setDescription('将振兴钱包的钱转到民生钱包，将收益钱包的钱转到普惠钱包');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始执行钱包余额迁移任务...');
        
        try {
            $zhenxingSuccessCount = 0;
            $zhenxingFailCount = 0;
            $zhenxingTotalAmount = 0;
            
            $shouyiSuccessCount = 0;
            $shouyiFailCount = 0;
            $shouyiTotalAmount = 0;

            // 1. 处理振兴钱包 -> 民生钱包
            $output->writeln('开始处理振兴钱包 -> 民生钱包...');
            
            // 先获取所有有振兴钱包余额的用户ID列表
            $zhenxingUserIds = User::where('zhenxing_wallet', '>', 0)
                ->column('id');
            
            $totalZhenxingUsers = count($zhenxingUserIds);
            $output->writeln("找到 {$totalZhenxingUsers} 个有振兴钱包余额的用户");
            
            if ($totalZhenxingUsers > 0) {
                // 使用 chunk 分批处理，每批处理100个用户
                $chunkSize = 100;
                $chunks = array_chunk($zhenxingUserIds, $chunkSize);
                $totalChunks = count($chunks);
                
                foreach ($chunks as $chunkIndex => $chunk) {
                    $output->writeln("处理第 " . ($chunkIndex + 1) . "/{$totalChunks} 批，共 " . count($chunk) . " 个用户...");
                    
                    foreach ($chunk as $userId) {
                        // 重新查询用户信息，确保获取最新余额
                        $user = User::where('id', $userId)
                            ->field('id, zhenxing_wallet')
                            ->find();
                        
                        if (!$user || $user['zhenxing_wallet'] <= 0) {
                            continue;
                        }
                        
                        $amount = $user['zhenxing_wallet'];
                        
                        Db::startTrans();
                        try {
                            // 减少振兴钱包余额
                            User::changeInc(
                                $user['id'],
                                -$amount, // 负数表示减少
                                'zhenxing_wallet',
                                15, // type: 管理员操作
                                0, // relation_id
                                14, // log_type: 振兴钱包
                                '钱包迁移：振兴钱包转到民生钱包',
                                0, // admin_user_id
                                2, // status: 已完成
                                'ZX', // sn_prefix
                                1 // is_delete
                            );
                            
                            // 增加民生钱包余额
                            User::changeInc(
                                $user['id'],
                                $amount, // 正数表示增加
                                'balance',
                                15, // type: 管理员操作
                                0, // relation_id
                                4, // log_type: 民生钱包
                                '振兴钱包汇入',
                                0, // admin_user_id
                                2, // status: 已完成
                                'MS' // sn_prefix
                            );
                            
                            $zhenxingTotalAmount += $amount;
                            $zhenxingSuccessCount++;
                            
                            Db::commit();
                        } catch (\Exception $e) {
                            Db::rollback();
                            $zhenxingFailCount++;
                            Log::error('振兴钱包迁移失败', [
                                'user_id' => $user['id'],
                                'amount' => $amount,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    
                    // 每批处理完后输出进度
                    $processed = ($chunkIndex + 1) * $chunkSize;
                    if ($processed > $totalZhenxingUsers) {
                        $processed = $totalZhenxingUsers;
                    }
                    $output->writeln("已处理 {$processed}/{$totalZhenxingUsers} 个用户");
                }
            }

            // 2. 处理收益钱包 -> 普惠钱包
            $output->writeln('开始处理收益钱包 -> 普惠钱包...');
            
            // 先获取所有有收益钱包余额的用户ID列表
            $shouyiUserIds = User::where('shouyi_wallet', '>', 0)
                ->column('id');
            
            $totalShouyiUsers = count($shouyiUserIds);
            $output->writeln("找到 {$totalShouyiUsers} 个有收益钱包余额的用户");
            
            if ($totalShouyiUsers > 0) {
                // 使用 chunk 分批处理，每批处理100个用户
                $chunkSize = 100;
                $chunks = array_chunk($shouyiUserIds, $chunkSize);
                $totalChunks = count($chunks);
                
                foreach ($chunks as $chunkIndex => $chunk) {
                    $output->writeln("处理第 " . ($chunkIndex + 1) . "/{$totalChunks} 批，共 " . count($chunk) . " 个用户...");
                    
                    foreach ($chunk as $userId) {
                        // 重新查询用户信息，确保获取最新余额
                        $user = User::where('id', $userId)
                            ->field('id, shouyi_wallet')
                            ->find();
                        
                        if (!$user || $user['shouyi_wallet'] <= 0) {
                            continue;
                        }
                        
                        $amount = $user['shouyi_wallet'];
                        
                        Db::startTrans();
                        try {
                            // 减少收益钱包余额
                            User::changeInc(
                                $user['id'],
                                -$amount, // 负数表示减少
                                'shouyi_wallet',
                                15, // type: 管理员操作
                                0, // relation_id
                                17, // log_type: 收益钱包
                                '钱包迁移：收益钱包转到普惠钱包',
                                0, // admin_user_id
                                2, // status: 已完成
                                'SY', // sn_prefix
                                1 // is_delete

                            );
                            
                            // 增加普惠钱包余额
                            User::changeInc(
                                $user['id'],
                                $amount, // 正数表示增加
                                'puhui',
                                15, // type: 管理员操作
                                0, // relation_id
                                13, // log_type: 普惠钱包
                                '收益钱包汇入',
                                0, // admin_user_id
                                2, // status: 已完成
                                'PH' // sn_prefix
                            );
                            
                            $shouyiTotalAmount += $amount;
                            $shouyiSuccessCount++;
                            
                            Db::commit();
                        } catch (\Exception $e) {
                            Db::rollback();
                            $shouyiFailCount++;
                            Log::error('收益钱包迁移失败', [
                                'user_id' => $user['id'],
                                'amount' => $amount,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    
                    // 每批处理完后输出进度
                    $processed = ($chunkIndex + 1) * $chunkSize;
                    if ($processed > $totalShouyiUsers) {
                        $processed = $totalShouyiUsers;
                    }
                    $output->writeln("已处理 {$processed}/{$totalShouyiUsers} 个用户");
                }
            }

            // 输出统计信息
            $output->writeln("========== 处理完成 ==========");
            $output->writeln("【振兴钱包 -> 民生钱包】");
            $output->writeln("成功: {$zhenxingSuccessCount} 个用户");
            $output->writeln("失败: {$zhenxingFailCount} 个用户");
            $output->writeln("总迁移金额: {$zhenxingTotalAmount}");
            $output->writeln("");
            $output->writeln("【收益钱包 -> 普惠钱包】");
            $output->writeln("成功: {$shouyiSuccessCount} 个用户");
            $output->writeln("失败: {$shouyiFailCount} 个用户");
            $output->writeln("总迁移金额: {$shouyiTotalAmount}");

        } catch (\Exception $e) {
            $output->writeln('执行出错：' . $e->getMessage());
            Log::error('钱包余额迁移任务执行异常：' . $e->getMessage());
        }
    }
}


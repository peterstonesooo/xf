<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;
use Exception;

class VoteDataSync extends Command
{
    private $sourceTable = 'mp_vote';
    private $targetTable = 'mp_vote_copy1';
    
    protected function configure()
    {
        $this->setName('vote:sync')
            ->setDescription('同步mp_vote表数据到mp_vote_copy1表并清空原表');
    }

    public function execute(Input $input, Output $output)
    {
        ini_set("memory_limit", "-1");
        set_time_limit(0);
        
        $output->writeln("=== 投票数据同步脚本 ===");
        $output->writeln("时间：" . date('Y-m-d H:i:s'));
        $output->writeln("");
        
        try {
            // 开启全局事务，确保整个流程的原子性
            Db::startTrans();
            
            try {
                // 记录同步前目标表的数据量
                $targetCountBefore = Db::table($this->targetTable)->count();
                $output->writeln("目标表同步前数据量：{$targetCountBefore}");
                
                // 1. 同步数据
                $syncedCount = $this->syncData($output);
                
                // 2. 验证同步结果
                $this->verifySync($output, $syncedCount, $targetCountBefore);
                
                // 3. 验证源表是否已清空（数据已在同步过程中逐条删除）
                $this->verifySourceTableCleared($output, $syncedCount);
                
                // 4. 最终验证
                $this->verifySync($output);
                
                // 提交事务
                Db::commit();
                
                $output->writeln("");
                $output->writeln("=== 同步流程完成 ===");
                $output->writeln("时间：" . date('Y-m-d H:i:s'));
                
            } catch (Exception $e) {
                // 回滚事务
                Db::rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $output->writeln("<error>同步流程失败：" . $e->getMessage() . "</error>");
            Log::error('Vote sync process failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    
    /**
     * 执行数据同步
     */
    private function syncData(Output $output)
    {
        try {
            $output->writeln("开始数据同步...");
            $output->writeln("源表：{$this->sourceTable}");
            $output->writeln("目标表：{$this->targetTable}");
            $output->writeln("");
            
            // 获取源表数据总数
            $totalCount = Db::table($this->sourceTable)->count();
            $output->writeln("源表共有 {$totalCount} 条数据");
            
            if ($totalCount == 0) {
                $output->writeln("源表为空，无需同步。");
                return 0;
            }
            
            // 获取当前时间，用于设置up_time字段
            $currentTime = date('Y-m-d H:i:s');
            
            // 使用chunk模式分批处理，类似Butie.php的写法
            $syncedCount = 0;
            
            Db::table($this->sourceTable)->order('id', 'asc')->chunk(100, function($records) use (&$syncedCount, $currentTime, $output) {
                foreach ($records as $record) {
                    $syncedCount++;
                    $output->writeln("正在同步第 {$syncedCount} 条数据...");
                    
                    // 准备目标表数据（除了id字段，添加up_time字段）
                    $targetData = [
                        'uid' => $record['uid'],
                        'phone' => $record['phone'],
                        'realname' => $record['realname'],
                        'title' => $record['title'],
                        'content' => $record['content'],
                        'vote_type' => $record['vote_type'],
                        'options' => $record['options'],
                        'status' => $record['status'],
                        'is_anonymous' => $record['is_anonymous'],
                        'max_votes' => $record['max_votes'],
                        'start_time' => $record['start_time'],
                        'end_time' => $record['end_time'],
                        'total_votes' => $record['total_votes'],
                        'view_count' => $record['view_count'],
                        'is_deleted' => $record['is_deleted'],
                        'create_time' => $record['create_time'],
                        'update_time' => $record['update_time'],
                        'up_time' => $currentTime
                    ];
                    
                    // 插入到目标表
                    Db::table($this->targetTable)->insert($targetData);
                    
                    // 立即删除源表中的这条记录
                    Db::table($this->sourceTable)->where('id', $record['id'])->delete();
                    
                    // 每100条显示一次进度
                    if ($syncedCount % 100 == 0) {
                        $output->writeln("已同步 {$syncedCount} 条数据");
                    }
                }
            });
            
            // 验证同步数量是否匹配
            if ($syncedCount != $totalCount) {
                throw new Exception("同步数量不匹配！源表：{$totalCount}条，已同步：{$syncedCount}条");
            }
            
            $output->writeln("");
            $output->writeln("<info>数据同步完成！共同步 {$syncedCount} 条数据到目标表。</info>");
            
            return $syncedCount;
            
        } catch (Exception $e) {
            $output->writeln("<error>数据同步失败：" . $e->getMessage() . "</error>");
            Log::error('Vote data sync failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 验证源表是否已清空（数据已在同步过程中逐条删除）
     */
    private function verifySourceTableCleared(Output $output, $expectedSyncedCount)
    {
        try {
            $output->writeln("");
            $output->writeln("验证源表是否已清空...");
            
            // 获取当前源表数据量
            $currentCount = Db::table($this->sourceTable)->count();
            $output->writeln("源表当前数据量：{$currentCount}");
            
            if ($currentCount == 0) {
                $output->writeln("<info>✅ 源表已完全清空！</info>");
            } else {
                throw new Exception("源表清空失败！仍有 {$currentCount} 条数据未处理");
            }
            
        } catch (Exception $e) {
            $output->writeln("<error>验证源表清空失败：" . $e->getMessage() . "</error>");
            Log::error('Verify source table cleared failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 验证同步结果
     */
    private function verifySync(Output $output, $expectedSyncedCount = null, $targetCountBefore = null)
    {
        try {
            $output->writeln("");
            $output->writeln("验证同步结果...");
            
            $sourceCount = Db::table($this->sourceTable)->count();
            $targetCount = Db::table($this->targetTable)->count();
            
            $output->writeln("源表数据量：{$sourceCount}");
            $output->writeln("目标表数据量：{$targetCount}");
            
            // 如果有同步前的目标表数据量，计算实际同步的数据量
            if ($targetCountBefore !== null && $expectedSyncedCount !== null) {
                $actualSyncedCount = $targetCount - $targetCountBefore;
                $output->writeln("目标表原有数据量：{$targetCountBefore}");
                $output->writeln("本次同步数据量：{$actualSyncedCount}");
                $output->writeln("预期同步数据量：{$expectedSyncedCount}");
                
                if ($actualSyncedCount == $expectedSyncedCount) {
                    $output->writeln("<info>✅ 同步验证成功：本次同步了 {$actualSyncedCount} 条数据到目标表。</info>");
                } else {
                    $output->writeln("<error>❌ 同步验证失败：实际同步 {$actualSyncedCount} 条，预期 {$expectedSyncedCount} 条。</error>");
                }
            } else {
                // 简单验证：源表为空且目标表有数据
                if ($sourceCount == 0 && $targetCount > 0) {
                    $output->writeln("<info>✅ 同步验证成功：源表已清空，目标表有数据。</info>");
                } elseif ($sourceCount > 0) {
                    $output->writeln("<info>ℹ️ 源表仍有 {$sourceCount} 条数据待同步。</info>");
                } else {
                    $output->writeln("<error>❌ 同步验证失败：源表和目标表都为空。</error>");
                }
            }
            
        } catch (Exception $e) {
            $output->writeln("<error>验证同步结果时出错：" . $e->getMessage() . "</error>");
        }
    }
}

<?php

namespace app\common\command;

use app\model\User;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
/**
 * 修正 mp_user_relation 数据，确保层级关系与用户上级链路一致
 * 预览：php think fix:user-relation --dry-run
 *  实际修复（默认 chunk=500，可调整）：php think fix:user-relation --chunk=300
 */
class FixUserRelation extends Command
{
    protected function configure()
    {
        $this->setName('fix:user-relation')
            ->setDescription('修正 mp_user_relation 数据，确保层级关系与用户上级链路一致')
            ->addOption('chunk', null, \think\console\input\Option::VALUE_OPTIONAL, '每批处理的用户数量', 500)
            ->addOption('dry-run', null, \think\console\input\Option::VALUE_NONE, '仅输出统计信息，不执行写操作');
    }

    protected function execute(Input $input, Output $output)
    {
        $chunkSize = (int) $input->getOption('chunk');
        if ($chunkSize <= 0) {
            $chunkSize = 500;
        }
        $dryRun = (bool) $input->getOption('dry-run');

        $output->writeln(sprintf(
            '<info>开始修正 mp_user_relation 数据 (chunk=%d, dry-run=%s)</info>',
            $chunkSize,
            $dryRun ? 'yes' : 'no'
        ));

        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'active_synced' => 0,
        ];

        $query = Db::name('user')
            ->field('id, up_user_id, is_active')
            ->order('id');

        $query->chunk($chunkSize, function ($users) use (&$stats, $dryRun, $output) {
            foreach ($users as $user) {
                $stats['processed']++;

                $expectedAncestors = User::getAllUpUserId($user['id']);
                $existingRows = UserRelation::where('sub_user_id', $user['id'])
                    ->order('level', 'asc')
                    ->select()
                    ->toArray();

                if (empty($expectedAncestors) && empty($existingRows)) {
                    $stats['skipped']++;
                    continue;
                }

                $levelMap = [];
                $duplicateIds = [];

                foreach ($existingRows as $row) {
                    $level = (int) $row['level'];
                    if (isset($levelMap[$level])) {
                        $duplicateIds[] = $row['id'];
                        continue;
                    }
                    $levelMap[$level] = $row;
                }

                if (!empty($duplicateIds)) {
                    if (!$dryRun) {
                        UserRelation::whereIn('id', $duplicateIds)->delete();
                    }
                    $stats['duplicates'] += count($duplicateIds);
                }

                foreach ($expectedAncestors as $level => $ancestorId) {
                    $level = (int) $level;
                    $ancestorId = (int) $ancestorId;

                    if (isset($levelMap[$level])) {
                        $row = $levelMap[$level];
                        $updates = [];

                        if ((int) $row['user_id'] !== $ancestorId) {
                            $updates['user_id'] = $ancestorId;
                        }
                        if ((int) $row['is_active'] !== (int) $user['is_active']) {
                            $updates['is_active'] = (int) $user['is_active'];
                            $stats['active_synced']++;
                        }

                        if (!empty($updates)) {
                            if (!$dryRun) {
                                UserRelation::where('id', $row['id'])->update($updates);
                            }
                            $stats['updated']++;
                        }
                        unset($levelMap[$level]);
                    } else {
                        if (!$dryRun) {
                            UserRelation::create([
                                'user_id' => $ancestorId,
                                'sub_user_id' => $user['id'],
                                'level' => $level,
                                'is_active' => (int) $user['is_active'],
                            ]);
                        }
                        $stats['created']++;
                    }
                }

                if (!empty($levelMap)) {
                    $idsToDelete = array_column($levelMap, 'id');
                    if (!$dryRun) {
                        UserRelation::whereIn('id', $idsToDelete)->delete();
                    }
                    $stats['deleted'] += count($idsToDelete);
                }
            }

            $output->writeln(sprintf(
                '<info>已处理用户总数: %d (新增:%d, 更新:%d, 删除:%d, 去重:%d, 跳过:%d)</info>',
                $stats['processed'],
                $stats['created'],
                $stats['updated'],
                $stats['deleted'],
                $stats['duplicates'],
                $stats['skipped']
            ));
        });

        $output->writeln('<info>修正完成</info>');
        $output->writeln(sprintf(
            '统计 => 处理:%d, 新增:%d, 更新:%d, 删除:%d, 去重:%d, 跳过:%d, 激活同步:%d',
            $stats['processed'],
            $stats['created'],
            $stats['updated'],
            $stats['deleted'],
            $stats['duplicates'],
            $stats['skipped'],
            $stats['active_synced']
        ));

        return 0;
    }
}


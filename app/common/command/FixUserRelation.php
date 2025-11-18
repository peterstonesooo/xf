<?php

namespace app\common\command;

use app\model\User;
use app\model\UserRelation;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
/**
 * 新增缺失的 mp_user_relation 关系数据
 * 预览：php think fix:user-relation --dry-run
 *  实际修复（默认 chunk=500，可调整）：php think fix:user-relation --chunk=300
 */
class FixUserRelation extends Command
{
    protected function configure()
    {
        $this->setName('fix:user-relation')
            ->setDescription('新增缺失的 mp_user_relation 关系数据')
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
            '<info>开始新增缺失的 mp_user_relation 关系数据 (chunk=%d, dry-run=%s)</info>',
            $chunkSize,
            $dryRun ? 'yes' : 'no'
        ));

        $stats = [
            'processed' => 0,
            'created' => 0,
            'skipped' => 0,
        ];

        $query = Db::name('user')
            ->field('id, up_user_id, is_active')
            ->order('id');

        $query->chunk($chunkSize, function ($users) use (&$stats, $dryRun, $output) {
            foreach ($users as $user) {
                $stats['processed']++;

                $expectedAncestors = User::getAllUpUserId($user['id']);
                
                if (empty($expectedAncestors)) {
                    $stats['skipped']++;
                    continue;
                }

                // 获取已存在的关系，用于检查哪些关系缺失
                $existingLevels = UserRelation::where('sub_user_id', $user['id'])
                    ->column('level');

                foreach ($expectedAncestors as $level => $ancestorId) {
                    $level = (int) $level;
                    $ancestorId = (int) $ancestorId;

                    // 只新增缺失的关系
                    if (!in_array($level, $existingLevels)) {
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
            }

            $output->writeln(sprintf(
                '<info>已处理用户总数: %d (新增:%d, 跳过:%d)</info>',
                $stats['processed'],
                $stats['created'],
                $stats['skipped']
            ));
        });

        $output->writeln('<info>处理完成</info>');
        $output->writeln(sprintf(
            '统计 => 处理:%d, 新增:%d, 跳过:%d',
            $stats['processed'],
            $stats['created'],
            $stats['skipped']
        ));

        return 0;
    }
}


<?php

namespace app\admin\controller;

use app\model\Setting;
use app\model\UserBalanceLog;
use think\facade\Cache;
use app\model\User;
use think\facade\Db;


class SettingController extends AuthController
{
    public function settingList()
    {
        $req = request()->param();

        $builder = Setting::order('sort', 'desc');
        if (isset($req['setting_id']) && $req['setting_id'] !== '') {
            $builder->where('id', $req['setting_id']);
        }
        if (isset($req['key']) && $req['key'] !== '') {
            $builder->where('key', $req['key']);
        }

        $data = $builder->where('is_show', 1)->select();

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showSetting()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = Setting::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function editSetting()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'value|配置值' => 'max:500',
        ]);
        $setting = Setting::where('id', $req['id'])->find();
        if(empty($setting)){
            return out(['code' => 1, 'msg' => '配置不存在']);
        }
        // 如果是图片上传类型的配置
        if (in_array($setting['key'], ['chat_group_id_qrcode-c','download_url_qrcode-c'])) {
            $req['value'] = upload_file('qrcode_img');
        }else{
           if(empty($req['value'])){
                return out(['code' => 1, 'msg' => '配置值不能为空']);
           }
        }

        $setting_conf=[];
        Setting::where('id', $req['id'])->update($req);
        $key = Setting::where('id',$req['id'])->value('key');
/*         if($key=='is_req_encypt'){
            config('config.is_req_encypt',$req['value']);
        } */
        $confArr=config('map.system_info.setting_key');
        $setting = Setting::whereIn("key",$confArr)->select();
        foreach($setting as $item){
            $setting_conf[$item['key']] = $item['value'];
        }
        Cache::set('setting_conf', json_decode(json_encode($setting_conf, JSON_UNESCAPED_UNICODE),true), 300);
        return out();
    }

    /**
     * 数据修正 - 用户余额日志重复数据
     */
    public function dataCorrection()
    {
        $req = request()->param();
        
        // 设置默认时间范围为2025年7月31号
        if (empty($req['start_date'])) {
            $req['start_date'] = '2025-07-31';
        }
        if (empty($req['end_date'])) {
            $req['end_date'] = '2025-07-31';
        }
        
        // 获取重复数据
        $duplicateData = $this->getDuplicateBalanceLogs($req);
        
        $this->assign('req', $req);
        $this->assign('data', $duplicateData);
        
        return $this->fetch();
    }

    /**
     * 获取重复的用户余额日志数据
     */
    private function getDuplicateBalanceLogs($req)
    {
        // 使用用户提供的SQL查询
        $sql = "SELECT 
    user_id, 
    type, 
    log_type, 
    relation_id, 
    change_balance, 
    DATE(created_at) as date, 
    COUNT(*) as duplicate_count 
FROM mp_user_balance_log 
    WHERE type IN (59, 67, 68,60,64)
    -- AND user_id IN (51513,51482)
GROUP BY user_id, type, log_type, relation_id, change_balance, DATE(created_at) 
HAVING COUNT(*) > 1 
ORDER BY user_id, type, log_type, relation_id, change_balance, date;";
        
        $params = [];
        
        
        
        // 执行查询
        $results = Db::query($sql, $params);
        // var_dump($results);
        // exit;
        foreach($results as $item){
            // 获取该组重复数据的详细信息，并删除重复数据剩一条。
            $duplicateRecords = Db::table('mp_user_balance_log')
                ->where('user_id', $item['user_id'])
                ->where('type', $item['type'])
                ->where('log_type', $item['log_type'])
                ->where('relation_id', $item['relation_id'])
                ->where('change_balance', $item['change_balance'])
                ->where('created_at', '>=', $item['date'] . ' 00:00:00')
                ->where('created_at', '<=', $item['date'] . ' 23:59:59')
                ->order('id', 'asc')
                ->select()->toArray();
            if(count($duplicateRecords) > 1){
                Db::startTrans();
                try{
                    
                    $firstRecord = array_shift($duplicateRecords);
                    $deleteIds = array_column($duplicateRecords, 'id');
                    Db::table('mp_user_balance_log')
                        ->whereIn('id', $deleteIds)
                        ->delete();
                        //金额回滚
                $user = User::where('id', $item['user_id'])->find();
                switch($item['log_type']){
                    case 5:
                        $wallet = "digit_balance";
                        break;
                    case 3:
                        $wallet = "butie";
                        break;
                    case 2:
                        $wallet = "team_bonus_balance";
                        break;
                    case 1:
                        $wallet = "topup_balance";
                        break;
                }
                if($user->$wallet < $item['change_balance']){
                    $user->$wallet = 0;
                }else{
                    $user->$wallet -= $item['change_balance'];
                }
                $user->save();
                Db::commit();
                }catch(\Exception $e){
                    Db::rollback();
                    var_dump($e->getMessage());
                    exit;
                    // return out(['code' => 1, 'msg' => '删除失败：' . $e->getMessage()]);
                }

                
            }
            
        }
        
        return $results;
    }

    /**
     * 删除重复数据
     */
    public function deleteDuplicateData()
    {
        $req = $this->validate(request(), [
            'user_id' => 'require|number',
            'type' => 'require|number',
            'log_type' => 'require|number',
            'relation_id' => 'require|number',
            'change_balance' => 'require',
            'date' => 'require',
        ]);

        try {
            // 获取该组重复数据
            $duplicateRecords = Db::table('mp_user_balance_log')
                ->where('user_id', $req['user_id'])
                ->where('type', $req['type'])
                ->where('log_type', $req['log_type'])
                ->where('relation_id', $req['relation_id'])
                ->where('change_balance', $req['change_balance'])
                ->where('created_at', '>=', $req['date'] . ' 00:00:00')
                ->where('created_at', '<=', $req['date'] . ' 23:59:59')
                ->order('id', 'asc')
                ->select();

            if (count($duplicateRecords) <= 1) {
                return out(['code' => 1, 'msg' => '没有重复数据需要删除']);
            }

            // 保留第一条记录，删除其他重复记录
            $firstRecord = array_shift($duplicateRecords);
            $deleteIds = array_column($duplicateRecords, 'id');

            Db::table('mp_user_balance_log')
                ->whereIn('id', $deleteIds)
                ->delete();

            return out(['code' => 0, 'msg' => '成功删除 ' . count($deleteIds) . ' 条重复数据']);
        } catch (\Exception $e) {
            return out(['code' => 1, 'msg' => '删除失败：' . $e->getMessage()]);
        }
    }

    /**
     * 批量删除重复数据
     */
    public function batchDeleteDuplicateData()
    {
        $req = $this->validate(request(), [
            'ids' => 'require|array',
        ]);

        try {
            $deletedCount = 0;
            
            foreach ($req['ids'] as $group) {
                $duplicateRecords = Db::table('mp_user_balance_log')
                    ->where('user_id', $group['user_id'])
                    ->where('type', $group['type'])
                    ->where('log_type', $group['log_type'])
                    ->where('relation_id', $group['relation_id'])
                    ->where('change_balance', $group['change_balance'])
                    ->where('created_at', '>=', $group['date'] . ' 00:00:00')
                    ->where('created_at', '<=', $group['date'] . ' 23:59:59')
                    ->order('id', 'asc')
                    ->select();

                if (count($duplicateRecords) > 1) {
                    $firstRecord = array_shift($duplicateRecords);
                    $deleteIds = array_column($duplicateRecords, 'id');

                    Db::table('mp_user_balance_log')
                        ->whereIn('id', $deleteIds)
                        ->delete();

                    $deletedCount += count($deleteIds);
                }
            }

            return out(['code' => 0, 'msg' => '成功删除 ' . $deletedCount . ' 条重复数据']);
        } catch (\Exception $e) {
            return out(['code' => 1, 'msg' => '批量删除失败：' . $e->getMessage()]);
        }
    }

    public function dataCorrection1()
    {
        $req = request()->param();
        
        $query = Db::table('mp_user_balance_log')
            ->where('type', 59)
            ->where('log_type','in' ,[3,4])
            ->where('remark', '购买商品每周分红')
            ->where('created_at', '>=', '2025-08-05 00:00:00')
            ->where('created_at', '<', '2025-08-06 00:00:00');
        
        // 添加搜索条件
        if (!empty($req['user_id'])) {
            $query->where('user_id', $req['user_id']);
        }
        
        if (!empty($req['log_type'])) {
            $query->where('log_type', $req['log_type']);
        }
        
        if (!empty($req['start_date'])) {
            $query->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        
        if (!empty($req['end_date'])) {
            $query->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        
        $list = $query->order('id', 'desc')->paginate(['query' => $req]);
        
        $this->assign('req', $req);
        $this->assign('list', $list);
        
        return $this->fetch();
    }

    /**
     * 修复购买商品每周分红数据
     */
    public function fixDataCorrection1()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);

        try {
            // 获取记录信息
            $record = Db::table('mp_user_balance_log')
                ->where('id', $req['id'])
                ->where('type', 59)
                ->where('log_type', 'in', [3,4])
                ->where('remark', '购买商品每周分红')
                ->where('created_at', '>=', '2025-08-05 00:00:00')
                ->where('created_at', '<', '2025-08-06 00:00:00')
                ->find();

            if (!$record) {
                return json(['code' => 1, 'msg' => '记录不存在或不符合修复条件', 'data' => null]);
            }

            Db::startTrans();
            try {
                // 1. 更新备注
                Db::table('mp_user_balance_log')
                    ->where('id', $req['id'])
                    ->update(['remark' => '购买商品每周分红-已修复']);

                // 2. 从digit_balance减掉钱
                $result1 = User::changeInc(
                    $record['user_id'], 
                    -$record['change_balance'], 
                    'digit_balance', 
                    104, 
                    $record['relation_id'], 
                    5, 
                    '购买商品每周分红-修复扣除'
                );

                // 3. 根据log_type确定目标钱包
                $targetWalletField = '';
                $targetLogType = 0;
                
                if ($record['log_type'] == 3) {
                    // 补贴钱包
                    $targetWalletField = 'butie';
                    $targetLogType = 3;
                } elseif ($record['log_type'] == 4) {
                    // 数字钱包
                    $targetWalletField = 'balance';
                    $targetLogType = 4;
                }

                // 4. 加到目标钱包
                $result2 = User::changeInc(
                    $record['user_id'], 
                    $record['change_balance'], 
                    $targetWalletField, 
                    104, 
                    $record['relation_id'], 
                    $targetLogType, 
                    '购买商品每周分红-修复转入'
                );

                // 检查操作结果
                if ($result1 !== 'success' || $result2 !== 'success') {
                    throw new \Exception('余额变动操作失败');
                }

                Db::commit();
                // 直接返回JSON响应
                return json(['code' => 0, 'msg' => '修复成功', 'data' => null]);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '修复失败：' . $e->getMessage(), 'data' => null]);
            }
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '操作失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 一键修复所有购买商品每周分红数据
     */
    public function batchFixDataCorrection1()
    {
        try {
            // 获取所有需要修复的记录
            $records = Db::table('mp_user_balance_log')
                ->where('type', 59)
                ->where('log_type', 'in', [3,4])
                ->where('remark', '购买商品每周分红')
                ->where('created_at', '>=', '2025-08-05 00:00:00')
                ->where('created_at', '<', '2025-08-06 00:00:00')
                ->select();

            if (empty($records)) {
                return json(['code' => 1, 'msg' => '没有需要修复的数据', 'data' => null]);
            }

            $successCount = 0;
            $failCount = 0;
            $errors = [];

            foreach ($records as $record) {
                Db::startTrans();
                try {
                    // 1. 更新备注
                    Db::table('mp_user_balance_log')
                        ->where('id', $record['id'])
                        ->update(['remark' => '购买商品每周分红-已修复']);

                    // 2. 从digit_balance减掉钱
                    $result1 = User::changeInc(
                        $record['user_id'], 
                        -$record['change_balance'], 
                        'digit_balance', 
                        104, 
                        $record['relation_id'], 
                        5, 
                        '购买商品每周分红-修复扣除'
                    );

                    // 3. 根据log_type确定目标钱包
                    $targetWalletField = '';
                    $targetLogType = 0;
                    
                    if ($record['log_type'] == 3) {
                        // 补贴钱包
                        $targetWalletField = 'butie';
                        $targetLogType = 3;
                    } elseif ($record['log_type'] == 4) {
                        // 数字钱包
                        $targetWalletField = 'balance';
                        $targetLogType = 4;
                    }

                    // 4. 加到目标钱包
                    $result2 = User::changeInc(
                        $record['user_id'], 
                        $record['change_balance'], 
                        $targetWalletField, 
                        104, 
                        $record['relation_id'], 
                        $targetLogType, 
                        '购买商品每周分红-修复转入'
                    );

                    // 检查操作结果
                    if ($result1 !== 'success' || $result2 !== 'success') {
                        throw new \Exception('余额变动操作失败');
                    }

                    Db::commit();
                    $successCount++;
                } catch (\Exception $e) {
                    Db::rollback();
                    $failCount++;
                    $errors[] = "记录ID {$record['id']}: " . $e->getMessage();
                }
            }

            $message = "批量修复完成！成功：{$successCount} 条，失败：{$failCount} 条";
            if (!empty($errors)) {
                $message .= "\n失败详情：" . implode("\n", array_slice($errors, 0, 5)); // 只显示前5个错误
                if (count($errors) > 5) {
                    $message .= "\n... 还有 " . (count($errors) - 5) . " 个错误";
                }
            }

            return json([
                'code' => 0, 
                'msg' => $message, 
                'data' => [
                    'success_count' => $successCount,
                    'fail_count' => $failCount,
                    'total_count' => count($records)
                ]
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '批量修复失败：' . $e->getMessage(), 'data' => null]);
        }
    }
}



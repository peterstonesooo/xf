<?php

namespace app\admin\controller;

use app\model\ExclusiveLog;
use app\model\User;
use think\facade\Db;

class ExclusiveLogController extends AuthController
{
    /**
     * 专属补贴申领记录列表
     */
    public function exclusiveLogList()
    {
        $req = request()->param();
        
        $builder = ExclusiveLog::alias('el')
            ->field('el.*, u.phone, u.realname, CONCAT("补贴类型", el.exclusive_setting_id) as setting_name')
            ->leftJoin('mp_user u', 'el.user_id = u.id')
            ->order('el.id', 'desc');

        // 搜索条件
        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('el.user_id', $req['user_id']);
        }
        
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', 'like', '%' . $req['phone'] . '%');
        }
        
        if (isset($req['exclusive_setting_id']) && $req['exclusive_setting_id'] !== '') {
            $builder->where('el.exclusive_setting_id', $req['exclusive_setting_id']);
        }
        
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('el.status', $req['status']);
        }
        
        if (!empty($req['start_date'])) {
            $builder->where('el.creat_time', '>=', $req['start_date'] . ' 00:00:00');
        }
        
        if (!empty($req['end_date'])) {
            $builder->where('el.creat_time', '<=', $req['end_date'] . ' 23:59:59');
        }

        $data = $builder->paginate(['query' => $req]);
        
        // 获取专属补贴设置列表（简化版本）
        $exclusiveSettings = [
            ['id' => 1, 'name' => '专属补贴类型1'],
            ['id' => 2, 'name' => '专属补贴类型2'],
            ['id' => 3, 'name' => '专属补贴类型3'],
            ['id' => 4, 'name' => '专属补贴类型4'],
            ['id' => 5, 'name' => '专属补贴类型5'],
        ];
        
        $this->assign('data', $data);
        $this->assign('req', $req);
        $this->assign('exclusiveSettings', $exclusiveSettings);
        
        return $this->fetch();
    }
    
    /**
     * 审核通过
     */
    public function auditPass()
    {
        $req = request()->post();
        
        $this->validate($req, [
            'id' => 'require|number',
            'minsheng_amount' => 'require|float|gt:0'
        ]);
        
        $exclusiveLog = ExclusiveLog::find($req['id']);
        if (!$exclusiveLog) {
            return out(null, 10001, '记录不存在');
        }
        
        if ($exclusiveLog['status'] != 0) {
            return out(null, 10001, '该记录已审核，不能重复审核');
        }
        
        Db::startTrans();
        try {
            // 更新审核状态和民生金金额
            ExclusiveLog::where('id', $req['id'])->update([
                'status' => 1,
                'minsheng_amount' => $req['minsheng_amount'],
                'creat_time' => date('Y-m-d H:i:s')
            ]);
            
            // 给用户增加收益金
            $user = User::find($exclusiveLog['user_id']);
            if ($user) {
                // 增加收益金到用户惠民钱包
                User::changeInc($exclusiveLog['user_id'], $req['minsheng_amount'], 'digit_balance', 63, $req['id'], 5, '专属补贴', 0, 1);
            }
            
            Db::commit();
            return out();
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '审核失败：' . $e->getMessage());
        }
    }
    
    /**
     * 审核拒绝
     */
    public function auditReject()
    {
        $req = request()->post();
        
        $this->validate($req, [
            'id' => 'require|number',
            'reason' => 'require|max:500'
        ]);
        
        $exclusiveLog = ExclusiveLog::find($req['id']);
        if (!$exclusiveLog) {
            return out(null, 10001, '记录不存在');
        }
        
        if ($exclusiveLog['status'] != 0) {
            return out(null, 10001, '该记录已审核，不能重复审核');
        }
        
        // 更新审核状态为拒绝
        ExclusiveLog::where('id', $req['id'])->update([
            'status' => 2,
            'reason' => $req['reason'],
            'creat_time' => date('Y-m-d H:i:s')
        ]);
        
        return out();
    }
    

} 
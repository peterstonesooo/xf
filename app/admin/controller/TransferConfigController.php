<?php

namespace app\admin\controller;

use app\model\TransferConfig;
use think\facade\Cache;

class TransferConfigController extends AuthController
{
    /**
     * 转账配置列表
     */
    public function index()
    {
        $req = request()->param();
        
        $builder = TransferConfig::order('wallet_type', 'asc');
        
        if (isset($req['wallet_type']) && $req['wallet_type'] !== '') {
            $builder->where('wallet_type', $req['wallet_type']);
        }
        
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }
        
        $data = $builder->select();
        
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('walletTypeMap', TransferConfig::$walletTypeMap);
        $this->assign('statusMap', TransferConfig::$statusMap);
        
        return $this->fetch();
    }
    
    /**
     * 添加转账配置页面
     */
    public function add()
    {
        $this->assign('walletTypeMap', TransferConfig::$walletTypeMap);
        return $this->fetch();
    }
    
    /**
     * 添加转账配置
     */
    public function save()
    {
        $req = $this->validate(request(), [
            'wallet_type|钱包类型' => 'require|in:1,2,3,4',
            'min_amount|最低转账金额' => 'require|float|gt:0',
            'max_amount|最高转账金额' => 'require|float|gt:0',
            'fee_rate|手续费率' => 'require|float|egt:0|elt:100',
            'daily_limit|日转账限额' => 'require|float|gt:0',
            'status|状态' => 'require|in:0,1',
            'remark|备注' => 'max:255',
        ]);
        
        // 检查最高金额是否大于最低金额
        if ($req['max_amount'] <= $req['min_amount']) {
            return out(null, 10001, '最高转账金额必须大于最低转账金额');
        }
        
        // 检查是否已存在该钱包类型的配置
        $exists = TransferConfig::where('wallet_type', $req['wallet_type'])->find();
        if ($exists) {
            return out(null, 10001, '该钱包类型已存在配置，请编辑现有配置');
        }
        
        $result = TransferConfig::create($req);
        
        if ($result) {
            // 清除缓存
            Cache::delete('transfer_config');
            return out();
        } else {
            return out(null, 10001, '添加失败');
        }
    }
    
    /**
     * 编辑转账配置页面
     */
    public function edit()
    {
        $req = request()->param();
        
        if (empty($req['id'])) {
            return $this->error('参数错误');
        }
        
        $data = TransferConfig::where('id', $req['id'])->find();
        if (empty($data)) {
            return $this->error('配置不存在');
        }
        
        $this->assign('data', $data);
        $this->assign('walletTypeMap', TransferConfig::$walletTypeMap);
        $this->assign('statusMap', TransferConfig::$statusMap);
        
        return $this->fetch();
    }
    
    /**
     * 更新转账配置
     */
    public function update()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'min_amount|最低转账金额' => 'require|float|gt:0',
            'max_amount|最高转账金额' => 'require|float|gt:0',
            'fee_rate|手续费率' => 'require|float|egt:0|elt:100',
            'daily_limit|日转账限额' => 'require|float|gt:0',
            'status|状态' => 'require|in:0,1',
            'remark|备注' => 'max:255',
        ]);
        
        // 检查最高金额是否大于最低金额
        if ($req['max_amount'] <= $req['min_amount']) {
            return out(null, 10001, '最高转账金额必须大于最低转账金额');
        }
        
        $config = TransferConfig::where('id', $req['id'])->find();
        if (empty($config)) {
            return out(null, 10001, '配置不存在');
        }
        
        $result = TransferConfig::where('id', $req['id'])->update($req);
        
        if ($result !== false) {
            // 清除缓存
            Cache::delete('transfer_config');
            return out();
        } else {
            return out(null, 10001, '更新失败');
        }
    }
    
    /**
     * 删除转账配置
     */
    public function delete()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        
        $config = TransferConfig::where('id', $req['id'])->find();
        if (empty($config)) {
            return out(null, 10001, '配置不存在');
        }
        
        $result = TransferConfig::where('id', $req['id'])->delete();
        
        if ($result) {
            // 清除缓存
            Cache::delete('transfer_config');
            return out();
        } else {
            return out(null, 10001, '删除失败');
        }
    }
    
    /**
     * 切换状态
     */
    public function toggleStatus()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
        ]);
        
        $config = TransferConfig::where('id', $req['id'])->find();
        if (empty($config)) {
            return out(null, 10001, '配置不存在');
        }
        
        $newStatus = $config['status'] == 1 ? 0 : 1;
        $result = TransferConfig::where('id', $req['id'])->update(['status' => $newStatus]);
        
        if ($result !== false) {
            // 清除缓存
            Cache::delete('transfer_config');
            return out();
        } else {
            return out(null, 10001, '状态切换失败');
        }
    }
}

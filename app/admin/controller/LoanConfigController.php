<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\LoanConfig;
use app\common\controller\BaseController;
use think\facade\Db;
use think\facade\View;

class LoanConfigController extends AuthController
{
    /**
     * 配置列表
     */
    public function configList()
    {
        $req = request()->param();
        
        $builder = LoanConfig::order('sort asc, id asc');
        
        // 搜索条件
        if (!empty($req['config_key'])) {
            $builder->where('config_key', 'like', '%' . $req['config_key'] . '%');
        }
        if (!empty($req['config_desc'])) {
            $builder->where('config_desc', 'like', '%' . $req['config_desc'] . '%');
        }
        if (isset($req['config_type']) && $req['config_type'] !== '') {
            $builder->where('config_type', $req['config_type']);
        }
        if (isset($req['is_show']) && $req['is_show'] !== '') {
            $builder->where('is_show', $req['is_show']);
        }
        
        $data = $builder->paginate(['query' => $req])->each(function ($item, $key) {
            $item->config_type_text = $item->getConfigTypeTextAttr(null, $item->toArray());
            $item->is_show_text = $item->getIsShowTextAttr(null, $item->toArray());
            return $item;
        });

        View::assign('req', $req);
        View::assign('data', $data);
        View::assign('configTypeMap', LoanConfig::$configTypeMap);
        View::assign('isShowMap', LoanConfig::$isShowMap);

        return View::fetch('loan_config/config_list');
    }

    /**
     * 显示配置表单
     */
    public function showConfig()
    {
        $id = request()->param('id');
        $data = null;
        
        if ($id) {
            $data = LoanConfig::find($id);
            if (!$data) {
                return $this->error('配置不存在');
            }
        }

        View::assign('data', $data);
        View::assign('configTypeMap', LoanConfig::$configTypeMap);
        View::assign('isShowMap', LoanConfig::$isShowMap);

        return View::fetch('loan_config/show_config');
    }

    /**
     * 保存配置
     */
    public function saveConfig()
    {
        try {
            $req = request()->param();
            
            // 验证数据
            $validate = [
                'config_key' => 'require|max:50',
                'config_value' => 'require',
                'config_desc' => 'max:200',
                'config_type' => 'require|in:text,number,select,textarea',
                'sort' => 'integer|egt:0',
                'is_show' => 'require|in:0,1'
            ];
            
            $this->validate($req, $validate);

            // 检查配置键是否重复
            $exists = LoanConfig::where('config_key', $req['config_key']);
            if (!empty($req['id'])) {
                $exists->where('id', '<>', $req['id']);
            }
            if ($exists->find()) {
                return out(null, 500, '配置键已存在');
            }

            Db::startTrans();
            try {
                if (!empty($req['id'])) {
                    // 更新
                    $config = LoanConfig::find($req['id']);
                    if (!$config) {
                        throw new \Exception('配置不存在');
                    }
                    $config->save($req);
                } else {
                    // 新增
                    $config = LoanConfig::create($req);
                }

                Db::commit();
                return out(null, 200, '保存成功');
            } catch (\Exception $e) {
                Db::rollback();
                return out(null, 500, '保存失败：' . $e->getMessage());
            }
        } catch (\Exception $e) {
            return out(null, 500, '保存失败：' . $e->getMessage());
        }
    }

    /**
     * 删除配置
     */
    public function deleteConfig()
    {
        $id = request()->param('id');
        
        if (!$id) {
            return out(null, 500, '参数错误');
        }

        Db::startTrans();
        try {
            $config = LoanConfig::find($id);
            if (!$config) {
                throw new \Exception('配置不存在');
            }

            // 检查是否为系统关键配置
            $criticalConfigs = ['loan_audit_auto', 'loan_auto_approve_amount', 'max_overdue_days'];
            if (in_array($config->config_key, $criticalConfigs)) {
                throw new \Exception('该配置为系统关键配置，不能删除');
            }

            $config->delete();

            Db::commit();
            return out(null, 200, '删除成功');
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 500, '删除失败：' . $e->getMessage());
        }
    }

    /**
     * 修改显示状态
     */
    public function changeShowStatus()
    {
        $id = request()->param('id');
        $isShow = request()->param('is_show');
        
        if (!$id || !in_array($isShow, [0, 1])) {
            return out(null, 500, '参数错误');
        }

        $config = LoanConfig::find($id);
        if (!$config) {
            return out(null, 500, '配置不存在');
        }

        $config->is_show = $isShow;
        $config->save();

        return out(null, 200, '状态修改成功');
    }

    /**
     * 批量更新配置
     */
    public function batchUpdate()
    {
        try {
            $req = request()->param();
            
            if (empty($req['configs']) || !is_array($req['configs'])) {
                return out(null, 500, '参数错误');
            }

            Db::startTrans();
            try {
                foreach ($req['configs'] as $configKey => $configValue) {
                    $config = LoanConfig::where('config_key', $configKey)->find();
                    if ($config) {
                        $config->config_value = $configValue;
                        $config->save();
                    }
                }

                Db::commit();
                return out(null, 200, '批量更新成功');
            } catch (\Exception $e) {
                Db::rollback();
                return out(null, 500, '批量更新失败：' . $e->getMessage());
            }
        } catch (\Exception $e) {
            return out(null, 500, '批量更新失败：' . $e->getMessage());
        }
    }

    /**
     * 出资钱包管理列表
     */
    public function investmentWalletList()
    {
        // 查询出资钱包配置
        $config = LoanConfig::where('config_key', 'investment_wallet_types')->find();
        
        if (!$config) {
            // 如果配置不存在，创建一个默认配置
            $config = LoanConfig::create([
                'config_key' => 'investment_wallet_types',
                'config_value' => '1,16,13,2,17',
                'config_desc' => '支持的出资钱包类型',
                'config_type' => 'select',
                'config_options' => json_encode([
                    "1" => "充值余额",
                    "2" => "荣誉钱包",
                    "3" => "稳盈钱包",
                    "4" => "民生钱包",
                    "5" => "惠民钱包",
                    "6" => "积分",
                    "7" => "幸福收益",
                    "8" => "稳赢钱包转入",
                    "9" => "抽奖卷",
                    "10" => "体验钱包预支金",
                    "11" => "体验钱包",
                    "12" => "幸福助力卷",
                    "13" => "普惠钱包",
                    "14" => "振兴钱包",
                    "15" => "投票奖励",
                    "16" => "共富钱包",
                    "17" => "收益钱包"
                ]),
                'sort' => 33,
                'is_show' => 1
            ]);
        }
        
        // 解析配置选项
        $walletOptions = [];
        if (!empty($config->config_options)) {
            $walletOptions = json_decode($config->config_options, true) ?: [];
        }
        
        // 解析已选中的钱包类型（保持顺序）
        $selectedWallets = [];
        $selectedWalletList = []; // 用于显示，包含ID和名称
        if (!empty($config->config_value)) {
            $selectedWallets = explode(',', $config->config_value);
            // 按照配置值中的顺序组织数据
            foreach ($selectedWallets as $walletId) {
                $walletId = trim($walletId);
                if (isset($walletOptions[$walletId])) {
                    $selectedWalletList[] = [
                        'id' => $walletId,
                        'name' => $walletOptions[$walletId]
                    ];
                }
            }
        }
        
        // 未选中的钱包（用于添加）
        $unselectedWallets = [];
        foreach ($walletOptions as $walletId => $walletName) {
            if (!in_array($walletId, $selectedWallets)) {
                $unselectedWallets[$walletId] = $walletName;
            }
        }
        
        View::assign('config', $config);
        View::assign('walletOptions', $walletOptions);
        View::assign('selectedWallets', $selectedWallets);
        View::assign('selectedWalletList', $selectedWalletList);
        View::assign('unselectedWallets', $unselectedWallets);
        View::assign('isShowMap', LoanConfig::$isShowMap);
        
        return View::fetch('loan_config/investment_wallet_list');
    }

    /**
     * 保存出资钱包配置
     */
    public function saveInvestmentWallet()
    {
        try {
            $req = request()->param();
            
            $config = LoanConfig::where('config_key', 'investment_wallet_types')->find();
            
            if (!$config) {
                return out(null, 500, '配置不存在');
            }
            
            // 更新配置值（选中的钱包类型，逗号分隔）
            if (isset($req['wallet_types']) && is_array($req['wallet_types'])) {
                $config->config_value = implode(',', $req['wallet_types']);
            } else {
                $config->config_value = '';
            }
            
            // 更新启用状态
            if (isset($req['is_show'])) {
                $config->is_show = intval($req['is_show']);
            }
            
            $config->save();
            
            return out(null, 200, '保存成功');
        } catch (\Exception $e) {
            return out(null, 500, '保存失败：' . $e->getMessage());
        }
    }

    /**
     * 保存出资钱包排序
     */
    public function saveInvestmentWalletSort()
    {
        try {
            $req = request()->param();
            
            $config = LoanConfig::where('config_key', 'investment_wallet_types')->find();
            
            if (!$config) {
                return out(null, 500, '配置不存在');
            }
            
            // 接收排序后的钱包ID数组
            if (isset($req['wallet_order']) && is_array($req['wallet_order'])) {
                // 过滤空值并保持顺序
                $walletOrder = array_filter($req['wallet_order'], function($id) {
                    return !empty($id);
                });
                $config->config_value = implode(',', $walletOrder);
                $config->save();
                return out(null, 200, '排序保存成功');
            } else {
                return out(null, 500, '参数错误');
            }
        } catch (\Exception $e) {
            return out(null, 500, '保存失败：' . $e->getMessage());
        }
    }
}

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
}

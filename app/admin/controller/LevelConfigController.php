<?php

namespace app\admin\controller;

use app\model\LevelConfig;

class LevelConfigController extends AuthController
{
    public function levelConfigList()
    {
        $req = request()->param();

        $builder = LevelConfig::order('id', 'asc');
        if (isset($req['level_config_id']) && $req['level_config_id'] !== '') {
            $builder->where('id', $req['level_config_id']);
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showLevelConfig()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = LevelConfig::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function editLevelConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'min_topup_amount|最小充值金额' => 'require|float',
            'min_direct_sub_active_num|直属下级激活人数' => 'require|integer',
            'topup_reward_ratio|充值奖励' => 'require|float',
            'cash_reward_amount|数字生活补贴' => 'require|float',
            'direct_recommend_reward_ratio|直属推荐奖' => 'require|float',
        ]);

        LevelConfig::where('id', $req['id'])->update($req);

        return out();
    }
}

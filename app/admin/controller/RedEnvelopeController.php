<?php

namespace app\admin\controller;

use app\model\RedEnvelopeUserLog;
use app\model\RedEnvelope;

class RedEnvelopeController extends AuthController
{
    //红包设置
    public function setting()
    {
        $this->assign('data', RedEnvelope::select()->toArray());
        return $this->fetch();  
    }

    public function signinPrizeAdd()
    {
        return $this->fetch();
    }

    //红包设置
    public function prizeSetting()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = RedEnvelope::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    //红包设置提交
    public function editConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'number',
            'number|金额' => 'require|number',
            // 'name|红包名称' => 'require',
        ]);

        
        if (!empty($req['id'])) {
            RedEnvelope::where('id', $req['id'])->update($req);
        } else {
            RedEnvelope::insert([
                'name' => $req['name'],
                'number' => $req['number'],
            ]);
        }
        

        return out();
    }

    //红包领取记录
    public function userLog()
    {
        $req = request()->param();
        $builder = RedEnvelopeUserLog::alias('l')->field('l.*, u.phone');

        if (isset($req['red_envelope_id']) && $req['red_envelope_id'] !== '') {
            $builder->where('l.red_envelope_id', $req['red_envelope_id']);
        }

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('l.user_id', $req['user_id']);
        }

        if (isset($req['user_phone']) && $req['user_phone'] !== '') {
            $builder->where('u.phone', $req['user_phone']);
        }
        
        $builder = $builder->leftJoin('mp_user u', 'l.user_id = u.id')->order('l.id', 'desc');
        $list = $builder->paginate(['query' => $req]);
        $prize = RedEnvelope::select();
        $this->assign('prize', $prize);
        $this->assign('data', $list);
        $this->assign('req', $req);
        return $this->fetch();
    }
    
    public function del()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        RedEnvelope::destroy($req['id']);
        return out();
    }
}

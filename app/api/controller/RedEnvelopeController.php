<?php

namespace app\api\controller;

use app\model\RedEnvelope;
use app\model\RedEnvelopeUserLog;
use think\facade\Cache;
use app\model\UserRelation;
use app\model\User;
use Exception;
use think\facade\Db;

class RedEnvelopeController extends AuthController
{
    public function redEnvelopeList()
    {
        $user = $this->user;
        $prizeList = RedEnvelope::order('id', 'asc')->select()->each(function($item) use($user) {
            $item['is_get'] = 0;
            if (RedEnvelopeUserLog::where('user_id', $user['id'])->where('red_envelope_id', $item['id'])->find()) {
                $item['is_get'] = 1;
            }
            return $item;
        });
        return out($prizeList);
    }

    /**
     * 领取红包
     */
    public function redEnvelope()
    {
        $user = $this->user;
        $clickRepeatName = 'redEnvelope-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $req = $this->validate(request(), [
            'id' => 'number'
        ]);

        if (dbconfig('red_envelope_switch') == 0) {
            return out(null, 10001, '该功能暂未开放');
        }

        if (!in_array($req['id'], [1, 2, 3, 4])) {
            return out(null, 10001, '参数错误');
        }

        $redEnvelope = RedEnvelope::find($req['id']);

        if ($user['level'] != $req['id']) {
            return out(null, 10001, '抱歉，您不可以领取该等级红包');
        }

        $isExists = RedEnvelopeUserLog::where('user_id', $user->id)->where('red_envelope_id', $redEnvelope['id'])->where('created_at', '>', date('Y-m-d H:i:s', strtotime(date('Y-m-d'))))->find();
        if ($isExists) {
            return out(null, 10001, '每天只能领取一次');
        }

        Db::startTrans();
        try {
            
            $userLogId = RedEnvelopeUserLog::insertGetId([
                'user_id' => $user->id,
                'red_envelope_id' => $redEnvelope['id'],
                'number' => $redEnvelope['number'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            User::changeInc($user->id,$redEnvelope['number'],'team_bonus_balance',35,$userLogId,2, '红包','',1,'RE');

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    /**
     * 领取记录
     */
    public function signinRecord()
    {
        $user = $this->user;

        $list = RedEnvelopeUserLog::alias('l')->leftJoin('mp_red_envelope e', 'e.id = l.red_envelope_id')->where('l.user_id', $user['id'])->order('l.id', 'desc')->select()->toArray();
        
        return out([
            'list' => $list
        ]);
    }

}
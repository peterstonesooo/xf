<?php

namespace app\model;

use think\Model;

class UserRelation extends Model
{
    public function getLevelTextAttr($value, $data)
    {
        $map = config('map.user_relation')['level_map'];
        return $map[$data['level']];
    }

    public function getIsActiveTextAttr($value, $data)
    {
        $map = config('map.user_relation')['is_active_map'];
        return $map[$data['is_active']];
    }

    public static function saveUserRelation($user_id)
    {
        $allUpUserIds = User::getAllUpUserId($user_id);
        foreach ($allUpUserIds as $k => $v) {
            UserRelation::create([
                'user_id' => $v,
                'sub_user_id' => $user_id,
                'level' => $k
            ]);
        }

        return true;
    }

    public function subUser()
    {
        return $this->belongsTo(User::class, 'sub_user_id')->field('id,realname,phone,created_at');
    }

    public static function  rankList($timeStr='today'){
        $reward = config('map.rank_reward');
        $relation = UserRelation::alias('r')
        ->field(['count(r.sub_user_id) as team_num', 'r.user_id','phone','realname'])
        ->join('user u', 'u.id = r.user_id')->where('r.is_active',1)
        ->whereTime('r.created_at',$timeStr)
        ->group('r.user_id')->order('team_num', 'desc')
        ->limit(100)->select()->toArray();
        foreach ($relation as $k => &$v) {
            $v['phone'] = substr_replace($v['phone'],'****', 3, 4);
            $v['sort'] = $k+1;
            if($k<=10){
                $v['reward'] = $reward[$k+1];
            }else{
                $v['reward'] = 20;
            }
        }
        return $relation;

    }
}

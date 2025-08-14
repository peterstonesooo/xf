<?php

namespace app\model;

use think\Model;

class Apply extends Model
{
    public static function add($userId,$type){
        $apply = Apply::where('user_id',$userId)->where('type',$type)->find();
        if($apply){
           return "已经申请过了";
        }
        $data = [
            'user_id'=>$userId,
            'type'=>$type,
        ];
        Apply::create($data);
        return "";
    }
}

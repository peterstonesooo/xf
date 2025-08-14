<?php

namespace app\model;

use think\Model;

class Certificate extends Model
{
    public static function getNo($user_id,$group_id)
    {
        $no = 'Q'.$user_id.$group_id.time().rand(1111,9999);
        //$no = sha1($no);
        return $no;
    }

    public static function getFormatTime($time){
        return [
            'year'=>date('Y',strtotime($time)),
            'month'=>date('m',strtotime($time)),
            'day'=>date('d',strtotime($time)),
        ];
    }
}

<?php

namespace app\model;

use think\Model;
use think\facade\Cache;

class Setting extends Model
{
    public static function getSetting(){
        $setting = Cache::get('setting_conf');
        if(!$setting){
            $confArr=config('map.system_info.setting_key');
            $setting = Setting::whereIn("key",$confArr)->select();
            foreach($setting as $item){
                $setting_conf[$item['key']] = $item['value'];
            }
            Cache::set('setting_conf', json_decode(json_encode($setting_conf, JSON_UNESCAPED_UNICODE),true), 300);
            return $setting_conf;
        }
        return $setting;
    }
}

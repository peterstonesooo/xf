<?php
declare (strict_types = 1);

namespace app\middleware;

use app\model\Setting;

class ReqWarp
{
    protected $noArr = [
        'common/uploadFile2',
        'common/paynotify',
        'common/payNotify',
        'common/paynotify2',
        'common/payNotify2',
        'common/paynotify3',
        'common/payNotify3',
        'common/paynotify4',
        'common/payNotify4',
        'common/paynotify5',
        'common/payNotify5',
        'common/paynotify6',
        'common/payNotify6',
        'common/paynotify7',
        'common/payNotify7',
        'common/paynotify8',
        'common/payNotify8',
        'common/paynotify9',
        'common/payNotify9',
        'common/paynotify10',
        'common/payNotify10',
        'common/paynotify11',
        'common/payNotify11',
        'common/paynotify12',
        'common/payNotify12',
        'common/paynotify13',
        'common/payNotify13',
        'common/payNotify_xinglian',
        'common/paynotify_xinglian',
        'common/paynotify_alinpay',
        'common/paynotify_alinpay',
        'common/payNotify_yunsf',
        'common/paynotify_yunsf',
        'common/paynotify_huitong',
        'cert/reject',
        'common/paynotifyfengxin',
        'common/paynotifyfengxiong',
        'common/paynotify_xxpay',
        'common/paynotifyjinhai',
        'common/paynotifyyiji',
        'common/paynotifymaimaitong',
        'common/paynotifyyiji1',
        'common/paynotify_dingbai',
        'common/paynotify_huichuang',
        'common/paynotify_shuihu',
        'common/test3',
        'common/isencrypt',

    ];
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        $pathInfo = $request->pathinfo();
        if(in_array(strtolower($pathInfo),$this->noArr)){
            return $next($request);
        }
        $setting=Setting::getSetting();
         //$setting['is_req_encypt'] = 0;
        if($setting['is_req_encypt']==1){
       
            $key=config('config.req_aes_key');
            $iv=config('config.req_aes_iv');
            $postData =[];
            $getData=[];
            if($request->has('d','post')){
                $d = $request->post('d');
                $jsonData = decryptAES($d,$key,$iv);
                $postData = json_decode($jsonData,true);
            }
            if($request->has('d','get')){
                $d=$request->get('d');
                $jsonData = decryptAES($d,$key,$iv);
                $postData = json_decode($jsonData,true);
            }
            $request = $request->withPost($postData)
                                ->withGet($getData);
        }
        return $next($request);
        
    }



}

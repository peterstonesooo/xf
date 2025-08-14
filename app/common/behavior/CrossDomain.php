<?php
/**
 * Created by PhpStorm.
 * User: Ns
 * Date: 2022/3/22
 * Time: 4:27 下午
 */

namespace app\common\behavior;

use Closure;

class CrossDomain
{
    public function handle($request, Closure $next)
    {
        header('Access-Control-Allow-Origin: '.request()->header('Origin'));
        header('Access-Control-Max-Age: 1800');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE');
        header("Access-Control-Allow-Credentials: true");
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Origin, Accept,Accept-Encoding,Accept-Language,Access-Control-Request-Headers,Access-Control-Request-Method,Referer,If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With, token, lang, sec-fetch-dest,sec-fetch-mode, sec-fetch-site,access-control_allow_origin,User-Agent,T,token,version_code,');
        if (strtoupper($request->method()) == "OPTIONS") {
            exit();
        }

        return $next($request);
    }
}

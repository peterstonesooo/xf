<?php
/**
 * Created by PhpStorm.
 * User: Ns
 * Date: 18-7-10
 * Time: 上午11:41
 */

namespace app\admin\controller;

use app\common\controller\BaseController;
use app\model\AdminUser;
use app\model\AuthGroupAccess;
use app\model\Country;
use gangsta\GoogleAuthenticator;
use think\facade\Db;
use think\facade\Session;

class CommonController extends BaseController
{
    public function login()
    {
        if (request()->isPost()){
            $req = request()->post();

            $adminUser = AdminUser::where('account', $req['account'])->find();
            if (empty($adminUser)){
                return out(null, 10001, '账号不存在');
            }
            if (md5(sha1($req['password'])) != $adminUser['password']){
                return out(null, 10001, '密码错误');
            }
            if($adminUser['status'] == 0){
                return out(null, 10001, '账号已被禁用');
            }
            if (AuthGroupAccess::where('admin_user_id', $adminUser['id'])->where('auth_group_id', 1)->count()) {
                Session::set('is_admin', 1);
            }else{
                Session::set('is_admin', 0);
            }
            
            //else{
            //     Session::set('is_agent', 0);
            //     Session::set('admin_user', $adminUser);
            //     Session::set('google_auth_secret', $adminUser);
            //     return out(['is_agent' => 0]);
            // }
            //Session::set('google_auth_secret', $adminUser);
            $auth = \app\model\Setting::where('key','is_google_auth')->find();
            if($auth['value'] == '1'){
                Session::set('google_auth_secret', $adminUser);
                return out(['isValid' => 1]);
            }else{
                Session::set('admin_user', $adminUser);
                return out(['isValid' => 0]);
            }
        }

        return $this->fetch();
    }

    public function secondaryValidation()
    {
        if (!Session::has('google_auth_secret')){
            $this->redirect('/admin/Common/login');
            exit;
        }

        $adminUser = Session::get('google_auth_secret');
        $ga = new GoogleAuthenticator();
        if (request()->isPost()){
            $req = request()->post();
            $this->validate($req, [
                'code|验证码' => 'require',
            ]);

            $adminUser = AdminUser::where('id', $adminUser['id'])->find();
            $checkResult = $ga->verifyCode($adminUser['google_auth_secret'], $req['code'], 2);
            if (!$checkResult && !in_array(env('common.environment', ''), ['local', 'test'])) {
                return out(null, 10001, '验证码错误');
            }

            Session::set('admin_user', $adminUser);

            return out();
        }

        if (empty($adminUser['google_auth_secret'])) {
            $google_auth_secret = $ga->createSecret();
            AdminUser::where('id', $adminUser['id'])->update(['google_auth_secret' => $google_auth_secret]);

            $qrCodeUrl = $ga->getQRCodeGoogleUrl(config('app.app_name').':'.$adminUser['account'], $google_auth_secret, 'googleVerify');

            $this->assign('url', $qrCodeUrl);
        }

        return $this->fetch('secondary_validation');
    }

    public function logout()
    {
        Session::clear();
        return redirect(url('admin/Common/login'));
    }

    public function doc()
    {
        $database = env('database.database');
        $prefix = env('database.prefix');
        $exclude_tables = "'".$prefix."admin_handle_log','".$prefix."auth_group','".$prefix."auth_group_access','".$prefix."auth_rule','".$prefix."admin_user'";

        $sql = "select TABLE_NAME name,TABLE_COMMENT comment from INFORMATION_SCHEMA.TABLES where TABLE_SCHEMA='".$database."' and TABLE_NAME not in (".$exclude_tables.")";
        $tables = Db::query($sql);
        $map1 = $map2 = [];
        $i = round(count($tables)/2);
        foreach ($tables as $k => $v) {
            $name = str_replace($prefix, '', $v['name']);
            if ($k >= $i) {
                $map1[$v['name']] = $name.'('.$v['comment'].')';
            }
            else {
                $map2[$v['name']] = $name.'('.$v['comment'].')';
            }
        }

        $data1 = [];
        foreach ($map1 as $k => $v){
            $sql = "select COLUMN_NAME name, DATA_TYPE type, COLUMN_COMMENT comment from INFORMATION_SCHEMA.COLUMNS where table_schema = '".$database."' AND table_name = '".$k."'";
            $comment = Db::query($sql);
            $data1[$v] = $comment;
        }

        $data2 = [];
        foreach ($map2 as $k => $v){
            $sql = "select COLUMN_NAME name, DATA_TYPE type, COLUMN_COMMENT comment from INFORMATION_SCHEMA.COLUMNS where table_schema = '".$database."' AND table_name = '".$k."'";
            $comment = Db::query($sql);
            $data2[$v] = $comment;
        }

        $this->assign('data1', $data1);
        $this->assign('data2', $data2);

        return $this->fetch();
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: Ns
 * Date: 18-7-10
 * Time: 上午11:42
 */

namespace app\admin\controller;

use app\common\controller\BaseController;
use app\model\AdminUser;
use think\facade\Session;

class AuthController extends BaseController
{
    public $adminUser = null;

    public function initialize()
    {
        if (!Session::has('admin_user')){
            $this->redirect(url('admin/Common/login'));
        }
        else {
            $adminUser = $this->adminUser = Session::get('admin_user');
            $adminUser = AdminUser::field('id,status')->where('id', $adminUser['id'])->find();
            if (empty($adminUser)){
                $this->error('您的账号已经被删除', url('admin/Common/login'));
            }
            if ($adminUser['status'] == 0){
                $this->error('您的账号已经被冻结', url('admin/Common/login'));
            }

            $controller = request()->controller();
            $action = request()->action();
            $path = $controller . '/' . $action;
            $path = strtolower($path);
/*             if (!session('is_admin') && !in_array($path, ['capital/withdrawlist', 'adminuser/updatepassword', 'adminuser/logout', 'adminuser/showupdatepassword', 'capital/auditwithdraw'])) {
                $this->redirect(url('admin/Capital/withdrawList'));
            } */

            if (!session('is_admin') && config('app.is_open_auth')){
                $action = request()->action();
                if (strpos($action, 'show') === false){
                    if (!AdminUser::checkAuth($adminUser['id'])) {
                        $this->error('您没有权限访问');
                    }
                }
            }
        }
    }
}
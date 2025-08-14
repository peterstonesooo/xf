<?php
/**
 * Created by PhpStorm.
 * User: Ns
 * Date: 19-7-5
 * Time: 下午4:10
 */

namespace app\api\controller;

use app\common\controller\BaseController;
use app\model\User;

class AuthController extends BaseController
{
    protected $user = null;

    protected function initialize()
    {
        parent::initialize();
        $this->user = User::getUserByToken();
        // 限制同一个用户，同一个接口，相同的参数，一秒之能请求一次
        check_repeat_request(1, 1, $this->user['id']);
    }
}

<?php

namespace app\model;

use think\Model;

class UserLoginLog extends Model
{
    protected $table = 'mp_user_login_log';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 设置字段信息
    protected $schema = [
        'id'            => 'int',
        'user_id'       => 'int',
        'phone'         => 'string',
        'login_type'    => 'int',
        'login_status'  => 'int',
        'fail_reason'   => 'string',
        'ip_address'    => 'string',
        'user_agent'    => 'string',
        'device_type'   => 'string',
        'device_info'   => 'string',
        'location'      => 'string',
        'login_time'    => 'datetime',
        'logout_time'   => 'datetime',
        'session_id'    => 'string',
        'remark'        => 'string',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // 登录方式映射
    public static $loginTypeMap = [
        1 => '手机号',
        2 => '邮箱',
        3 => '用户名',
        4 => '其他',
    ];

    // 登录状态映射
    public static $loginStatusMap = [
        1 => '成功',
        2 => '失败',
    ];

    /**
     * 获取登录方式文本
     */
    public function getLoginTypeTextAttr($value, $data)
    {
        return self::$loginTypeMap[$data['login_type'] ?? 1] ?? '未知';
    }

    /**
     * 获取登录状态文本
     */
    public function getLoginStatusTextAttr($value, $data)
    {
        return self::$loginStatusMap[$data['login_status'] ?? 1] ?? '未知';
    }

    /**
     * 记录登录信息
     * @param array $data 登录数据
     * @return UserLoginLog
     */
    public static function recordLogin($data)
    {
        return self::create([
            'user_id'      => $data['user_id'] ?? 0,
            'phone'        => $data['phone'] ?? null,
            'login_type'   => $data['login_type'] ?? 1,
            'login_status' => $data['login_status'] ?? 1,
            'fail_reason'  => $data['fail_reason'] ?? null,
            'ip_address'   => $data['ip_address'] ?? null,
            'user_agent'   => $data['user_agent'] ?? null,
            'device_type'  => $data['device_type'] ?? null,
            'device_info'  => $data['device_info'] ?? null,
            'location'     => $data['location'] ?? null,
            'login_time'   => $data['login_time'] ?? date('Y-m-d H:i:s'),
            'session_id'   => $data['session_id'] ?? null,
            'remark'       => $data['remark'] ?? null,
        ]);
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


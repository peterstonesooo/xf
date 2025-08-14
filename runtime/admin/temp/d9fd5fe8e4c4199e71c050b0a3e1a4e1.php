<?php /*a:4:{s:75:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/user/user_list.html";i:1754675894;s:67:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/layout.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/head.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/left.html";i:1754294218;}*/ ?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo config('app.app_name'); ?>管理系统</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <link rel="stylesheet" href="/admin/bower_components/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/admin/bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="/admin/bower_components/Ionicons/css/ionicons.min.css">
    <link rel="stylesheet" href="/admin/dist/css/AdminLTE.min.css">
    <link rel="stylesheet" href="/admin/dist/css/skins/skin-blue.min.css">
    <link rel="stylesheet" href="/admin/css/custom.css">

    <script src="/admin/dist/js/respond.js"></script>
    <script src="/admin/dist/js/html5shiv.min.js"></script>

    <script src="/admin/bower_components/jquery/dist/jquery.min.js"></script>
    <script src="/admin/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>

    <script src="/admin/bower_components/jquery-slimscroll/jquery.slimscroll.min.js"></script>

    <script src="/admin/dist/js/adminlte.min.js"></script>
    <script src="/admin/plugins/layui/layer/layer.js"></script>
    <script src="/admin/plugins/laydate/laydate.js"></script>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
</head>


<body class="hold-transition skin-blue fixed sidebar-mini">

<div id="wrapper">

    <header class="main-header">
        <a href="javascript::" class="logo">
            <span class="logo-mini"><b><?php echo config('app.app_name'); ?></b></span>
            <span class="logo-lg"><b><?php echo config('app.app_name'); ?>管理系统</b></span>
        </a>
        <nav class="navbar navbar-static-top" role="navigation">
            <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                <span class="sr-only">切换导航</span>
            </a>
            <div class="navbar-custom-menu">
                <ul class="nav navbar-nav">
                    <li class="dropdown user user-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <img src="/admin/img/logo.jpg" class="user-image">
                            <span class="hidden-xs"><?php echo session('admin_user')['nickname']; ?></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="user-header">
                                <img src="/admin/img/logo.jpg" class="img-circle">
                                <h4><?php echo session('admin_user')['nickname']; ?></h4>
                            </li>
                            <li class="user-body">
                                <div class="pull-left">
                                    <button class="btn btn-default btn-flat" onclick="updatePassword()">修改密码</button>
                                </div>
                                <div class="pull-right">
                                    <a href="<?php echo url('admin/Common/logout'); ?>" class="btn btn-default btn-flat">退出登录</a>
                                </div>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
    </header>

    <aside class="main-sidebar">

    <section class="sidebar">
        <div class="user-panel">
            <div class="pull-left image">
                <img src="/admin/img/logo.jpg" class="img-circle">
            </div>
            <div class="pull-left info">
                <p><?php echo !empty(session('admin_user')['nickname']) ? session('admin_user')['nickname'] : session('admin_user')['account']; ?></p>
                <i class="fa fa-circle text-success"></i> 在线
            </div>
        </div>
        <?php $url = app('http')->getName().'/'.request()->controller().'/'.request()->action(); $url = url($url); ?>
        <ul class="sidebar-menu" data-widget="tree">
            <li class="header">主导航</li>
            <?php if (session('is_agent')) { ?>
                <li class="menu-li active">
                    <a href="<?php echo url('admin/Capital/withdrawList'); ?>">
                        <i class="fa fa-money"></i>
                        <span>提现列表</span>
                    </a>
                </li>
            <?php } else { $authMenuNavigation = auth_show_navigation(); foreach ($authMenuNavigation as $k => $v){ if (is_array($v['url'])){ $actionArr = []; foreach ($v['url'] as $k1 => $v1){ $actionArr[] = url($v1['url']); } ?>
                    <li class="menu-li treeview <?php if (in_array($url, $actionArr)){ ?> active <?php }?>">
                        <a href="#">
                            <i class="fa <?php echo htmlentities($v['icon']); ?>"></i>
                            <span><?php echo htmlentities($k); ?></span>
                            <span class="pull-right-container">
                        <i class="fa fa-angle-left pull-right"></i>
                    </span>
                        </a>
                        <ul class="treeview-menu">
                            <?php foreach ($v['url'] as $k1 => $v1){ ?>
                                <li class="menu-li">
                                    <a href="<?php echo url($v1['url']); ?>">
                                        <i class="fa <?php echo htmlentities($v1['icon']); ?>"></i>
                                        <span><?php echo htmlentities($k1); ?></span>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </li>
                <?php } else { ?>
                    <li class="menu-li <?php if ($url == url($v['url'])){ ?> active <?php }?>">
                        <a href="<?php echo url($v['url']); ?>">
                            <i class="fa <?php echo htmlentities($v['icon']); ?>"></i>
                            <span><?php echo htmlentities($k); ?></span>
                        </a>
                    </li>
                <?php } } } ?>
        </ul>
    </section>

</aside>


    <div class="content-wrapper">
        <section class="content-header">
            <h1 id="content-header-title"></h1>
        </section>

        <section class="content container-fluid" style="font-size: 15px;">
            <div class="search">
    <form class="form-inline">
        <input type="text" value="<?php echo isset($req['user_id']) ? htmlentities($req['user_id']) : ''; ?>" name="user_id" placeholder="搜索用户ID" class="form-control">
        <input type="text" value="<?php echo isset($req['up_user']) ? htmlentities($req['up_user']) : ''; ?>" name="up_user" placeholder="搜索上级用户ID/手机号"
               class="form-control">
        <input type="text" value="<?php echo isset($req['phone']) ? htmlentities($req['phone']) : ''; ?>" name="phone" placeholder="搜索手机号" class="form-control">
        <input type="text" value="<?php echo isset($req['invite_code']) ? htmlentities($req['invite_code']) : ''; ?>" name="invite_code" placeholder="搜索邀请码" class="form-control">
        <input type="text" value="<?php echo isset($req['realname']) ? htmlentities($req['realname']) : ''; ?>" name="realname" placeholder="搜索实名姓名" class="form-control">
        <input type="text" value="<?php echo isset($req['ic_number']) ? htmlentities($req['ic_number']) : ''; ?>" name="ic_number" placeholder="搜索身份证号" class="form-control">
        <!-- <select name="level" class="form-control">
            <option value="">搜索等级</option>
            <?php foreach (config('map.user')['level_map'] as $k => $v) { ?>
                <option <?php if (isset($req['level']) && $req['level'] !== '' && $req['level'] == $k) {
                    echo 'selected = "selected"';
                } ?> value="<?php echo htmlentities($k); ?>"><?php echo htmlentities($v); ?>
                </option>
            <?php } ?>
        </select> -->
        <select name="is_active" class="form-control">
            <option value="">搜索是否激活</option>
            <option <?php if (isset($req['is_active']) && $req['is_active'] !== '' && $req['is_active'] == 0) {
                echo 'selected = "selected"';
            } ?> value="0">未激活
            </option>
            <option <?php if (isset($req['is_active']) && $req['is_active'] !== '' && $req['is_active'] == 1) {
                echo 'selected = "selected"';
            } ?> value="1">已激活
            </option>
        </select>
        <input placeholder="开始时间" autocomplete="off" value="<?php echo isset($req['start_date']) ? htmlentities($req['start_date']) : ''; ?>" name="start_date" class="form-control layer-date" id="start">
        ～
        <input placeholder="结束时间" autocomplete="off" value="<?php echo isset($req['end_date']) ? htmlentities($req['end_date']) : ''; ?>" name="end_date" class="form-control layer-date" id="end">
        <input placeholder="激活时间" autocomplete="off" value="<?php echo isset($req['active_date']) ? htmlentities($req['active_date']) : ''; ?>" name="active_date" class="form-control layer-date" id="active_date">
        <input class="btn btn-flat btn-primary m_10" type="submit" value="搜索">
        <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;共 <?php echo htmlentities($count); ?> 条记录</span>
        <!-- <a href="<?php echo url('admin/User/testRedis'); ?>" class="btn btn-flat btn-warning m_10">测试Redis</a> -->
    </form>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header">
                <div class="box-body table-responsive no-padding">
                    <table class="table table-bordered table-hover table-striped">
                        <tbody>
                        <tr>
                            <th>ID</th>
                            <th>上级会员</th>
                            <th style="min-width: 190px;">会员信息</th>
                            <th style="min-width: 180px;">会员钱包</th>
                            <th>等级</th>
                            <th style="min-width: 70px;">实名</th>
                            <th>激活</th>
                            <th>激活时间</th>
                            <th style="min-width: 180px;">团队信息</th>
                            <!-- <th>总投资</th> -->
                            <th>时间</th>
                            <th>状态</th>
                            <th>团队封禁</th>
                            <!-- <th>首码</th> -->
                            <th>操作</th>
                        </tr>
                        <?php foreach ($data as $k => $v) { ?>
                            <tr>
                                <td><?php echo htmlentities($v['id']); ?></td>
                                <td><?php echo isset($v['upUser']['phone']) ? htmlentities($v['upUser']['phone']) : ''; ?></td>
                                <td>
                                    手机号：<?php echo htmlentities($v['phone']); ?>
                                    <br>
                                    邀请码：<?php echo htmlentities($v['invite_code']); ?>
                                    <br>
                                    实名：<?php echo htmlentities($v['realname']); ?>
                                    <br>
                                    身份证：<?php echo htmlentities($v['ic_number']); ?>
                                </td>
                                <td>
                                    余额：<?php echo htmlentities($v['topup_balance']); ?>
                                    <br>
                                    荣誉钱包：<?php echo htmlentities($v['team_bonus_balance']); ?>
                                    <br>
                                    稳盈钱包：<?php echo htmlentities($v['butie']); ?>
                                    <br>
                                    民生钱包：<?php echo htmlentities($v['balance']); ?>
                                    <br>
                                    收益钱包：<?php echo htmlentities($v['digit_balance']); ?>
                                    <br>
                                    增值钱包：<?php echo htmlentities($v['appreciating_wallet']); ?>
                                    <br>
                                    积分：<?php echo htmlentities($v['integral']); ?>
                                </td>
                                <td><?php echo htmlentities($v['level_text']); ?></td>
                                <td><?php echo htmlentities($v['shiming_status_text']); ?></td>
                                <td><?php echo htmlentities($v['is_active_text']); ?></td>
                                <td><?php echo htmlentities($v['active_date']); ?></td>
                                <td>
                                    直属下级实名：<a href="<?php echo url('admin/User/userList', ['up_user' => $v['id'], 'shiming_status' => 1]); ?>"><?php echo htmlentities($v['real_sub_user_num']); ?></a>
                                    <br>
                                    团队下级人数：<a href="<?php echo url('admin/User/userList', ['tm_up_user' => $v['id']]); ?>"><?php echo htmlentities($v['sub_user_num']); ?></a>
                                    <br>
                                    今日直属下级实名：<a href="<?php echo url('admin/User/userList', ['up_user' => $v['id'], 'shiming_status' => 1, 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d')]); ?>"><?php echo htmlentities($v['real_sub_user_num_today']); ?></a>
                                </td>
                                
                                <!-- <td><?php echo htmlentities($v['invest_amount']); ?></td> -->
                                <td><?php echo htmlentities($v['created_at']); ?></td>
                                <td>
                                    <div class="switch">
                                        <div class="onoffswitch">
                                            <input type="checkbox" <?php echo $v['status'] == 1 ? 'checked' : ''; ?>
                                                   class="onoffswitch-checkbox" id="status<?php echo htmlentities($v['id']); ?>">
                                            <label class="onoffswitch-label" for="status<?php echo htmlentities($v['id']); ?>"
                                                   onclick="changeUser(<?php echo htmlentities($v['id']); ?>, 'status')">
                                                <span class="onoffswitch-inner"></span>
                                                <span class="onoffswitch-switch"></span>
                                            </label>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="switch">
                                        <div class="onoffswitch">
                                            <input type="checkbox" <?php echo $v['team_ban_status'] == 1 ? 'checked' : ''; ?>
                                                   class="onoffswitch-checkbox" id="teamBan<?php echo htmlentities($v['id']); ?>">
                                            <label class="onoffswitch-label" for="teamBan<?php echo htmlentities($v['id']); ?>"
                                                   onclick="banUserAndTeam(<?php echo htmlentities($v['id']); ?>, $('#teamBan<?php echo htmlentities($v['id']); ?>').is(':checked') ? 'ban' : 'unban')">
                                                <span class="onoffswitch-inner"></span>
                                                <span class="onoffswitch-switch"></span>
                                            </label>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a <?php echo auth_show_judge('User/editUser'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/showUser', ['id' => $v['id']]); ?>">编辑用户</a>
                                    <a <?php echo auth_show_judge('User/addBalance'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/showChangeBalance', ['user_id' => $v['id'], 'type' => 15]); ?>">手动入金</a>
                                    <a <?php echo auth_show_judge('User/addOrder'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/showChangeOrder', ['user_id' => $v['id'], 'type' => 15]); ?>">赠送产品</a>
                                    <!-- <a <?php echo auth_show_judge('User/addJiaoNa'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/showChangeJiaoNa', ['user_id' => $v['id']]); ?>">赠送缴纳</a> -->
                                    <!-- <a <?php echo auth_show_judge('User/batchBalance'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/batchShowBalance', ['user_id' => $v['id'], 'type' => 15]); ?>">批量入金</a> -->
                                    <!-- <a <?php echo auth_show_judge('User/deductBalance'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/showChangeBalance', ['user_id' => $v['id'], 'type' => 16]); ?>">手动出金</a> -->
                                    <a <?php echo auth_show_judge('User/userTeamList'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/userTeamList', ['user_id' => $v['id']]); ?>">查看团队人数</a>
                                     <a <?php echo auth_show_judge('UserDelivery/userDeliveryList'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/UserDelivery/userDeliveryList', ['user_id' => $v['id']]); ?>">查看收货地址</a>
                                    <!--<a <?php echo auth_show_judge('PayAccount/payAccountList'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/PayAccount/payAccountList', ['user_id' => $v['id']]); ?>">查看收款配置</a> -->
                                    <a <?php echo auth_show_judge('User/editPhone'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/editPhone', ['user_id' => $v['id']]); ?>">修改手机号</a>
                                    <a <?php echo auth_show_judge('User/editPhone'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/editRelease', ['user_id' => $v['id']]); ?>">修改释放提现额度</a>
                                    <!-- <a <?php echo auth_show_judge('User/bankList'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/bankList', ['user_id' => $v['id']]); ?>">收款方式列表</a> -->
                                    <a <?php echo auth_show_judge('User/updateBank'); ?> class="btn btn-flat btn-xs" onclick="showAuthenticationModal(<?php echo htmlentities($v['id']); ?>)">实名认证</a>
                                    <!-- <a <?php echo auth_show_judge('User/openAuth'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/authentication', ['user_id' => $v['id']]); ?>">实名认证</a> -->
                                    <!-- <a <?php echo auth_show_judge('User/homelandList'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/homelandList', ['user_id' => $v['id']]); ?>">户口认证</a> -->
                                    <!-- <a <?php echo auth_show_judge('User/reserveHouse'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/reserveHouse', ['user_id' => $v['id']]); ?>">预约看房</a>
                                    <a <?php echo auth_show_judge('User/reserveCar'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/reserveCar', ['user_id' => $v['id']]); ?>">预约看车</a> -->
                                    <!-- <a <?php echo auth_show_judge('User/userNums'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/userNums', ['user_id' => $v['id']]); ?>">产品/人数</a>
                                    <a <?php echo auth_show_judge('User/userNums'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/jiaoNaNums', ['user_id' => $v['id']]); ?>">缴纳人数</a> -->
<!--                                    <a <?php echo auth_show_judge('User/homeland'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/wallet', ['user_id' => $v['id']]); ?>">收货地址</a>-->
                                    <!-- <a <?php echo auth_show_judge('User/editBank'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/editBank', ['user_id' => $v['id']]); ?>">修改银行卡</a> -->
                                    <a <?php echo auth_show_judge('User/message'); ?> class="btn btn-flat btn-xs" href="<?php echo url('admin/User/message', ['user_id' => $v['id']]); ?>">发送站内信</a>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align:center;font-size: 14px;"><?php echo $data->render(); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- 实名认证弹框 -->
<div class="modal fade" id="authenticationModal" tabindex="-1" role="dialog" aria-labelledby="authenticationModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="authenticationModalLabel">实名认证信息</h4>
            </div>
            <form id="authenticationForm">
                <div class="modal-body">
                    <input type="hidden" id="auth_user_id" name="user_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="auth_realname">姓名</label>
                                <input type="text" class="form-control" id="auth_realname" name="realname" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="auth_phone">手机号</label>
                                <input type="text" class="form-control" id="auth_phone" name="phone" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="auth_ic_number">身份证号</label>
                                <input type="text" class="form-control" id="auth_ic_number" name="ic_number" placeholder="请输入身份证号">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="auth_bank_card">银行卡号</label>
                                <input type="text" class="form-control" id="auth_bank_card" name="bank_card" placeholder="请输入银行卡号">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="auth_card_front">身份证正面照</label>
                                <input type="file" class="form-control" id="auth_card_front_file" name="card_front_file" accept="image/*">
                                <input type="hidden" id="auth_card_front" name="card_front">
                                <div id="card_front_preview" style="margin-top: 10px;"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="auth_card_back">身份证反面照</label>
                                <input type="file" class="form-control" id="auth_card_back_file" name="card_back_file" accept="image/*">
                                <input type="hidden" id="auth_card_back" name="card_back">
                                <div id="card_back_preview" style="margin-top: 10px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">提交</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
        $(function () {
        //日期范围限制
        var start = {
            elem: '#start',
            format: 'yyyy-MM-dd',
        };
        var end = {
            elem: '#end',
            format: 'yyyy-MM-dd',
        };
        var active_date = {
            elem: '#active_date',
            format: 'yyyy-MM-dd',
        };
        laydate.render(start);
        laydate.render(end);
        laydate.render(active_date);
    });
    function changeUser(id, field)
    {
        var value = 0;
        if ($('#' + field + id).is(':checked')) {
            value = 0;
        } else {
            value = 1;
        }

        //确认框
        layer.confirm('确定操作吗', {icon: 3, title: '提示'}, function (index) {
            layer.close(index);
            $.post('<?php echo url("admin/User/changeUser"); ?>', {"id": id, "value": value, "field": field}, function (res) {
                if (res.code == 200) {
                    // 不再同步团队封禁开关
                } else {
                    if (value == 1) {
                        $('#' + field + id).prop('checked', false);
                    } else {
                        $('#' + field + id).prop('checked', true);
                    }
                    layer.msg(res.msg, {icon: 5, time: 2500, offset: '80px'});
                }
            });
        }, function (index2) {
            if (value == 1) {
                $('#' + field + id).prop('checked', false);
            } else {
                $('#' + field + id).prop('checked', true);
            }
        });
    }

    // 一键封禁/解封用户及其团队
    function banUserAndTeam(userId, action) {
        var actionText = action == 'ban' ? '封禁' : '解封';
        var confirmText = action == 'ban' ? '封禁' : '解封';
        
        var checkbox = $('#teamBan' + userId);
        var currentChecked = checkbox.is(':checked');
        
        layer.confirm('确定要' + confirmText + '该用户及其所有下属团队成员吗？此操作不可逆！', {
            icon: 3, 
            title: '警告',
            btn: ['确定' + confirmText, '取消']
        }, function (index) {
            layer.close(index);
            $.post('<?php echo url("admin/User/banUserAndTeam"); ?>', {"user_id": userId, "action": action}, function (res) {
                if (res.code == 200) {
                    // 不再同步状态开关
                    layer.msg(res.msg, {icon: 1, time: 3000, offset: '80px'}, function() {
                        window.location.reload();
                    });
                } else {
                    checkbox.prop('checked', currentChecked);
                    layer.msg(res.msg, {icon: 2, time: 2500, offset: '80px'});
                }
            });
        }, function (index2) {
            checkbox.prop('checked', currentChecked);
        });
    }

    // 显示实名认证弹框
    function showAuthenticationModal(userId) {
        // 获取用户信息
        $.get('<?php echo url("admin/User/getUserAuthInfo"); ?>', {user_id: userId}, function(res) {
            if (res.code == 200) {
                var data = res.data;
                $('#auth_user_id').val(userId);
                $('#auth_realname').val(data.realname || '');
                $('#auth_phone').val(data.phone || '');
                $('#auth_ic_number').val(data.ic_number || '');
                $('#auth_bank_card').val(data.bank_card || '');
                $('#auth_card_front').val(data.card_front || '');
                $('#auth_card_back').val(data.card_back || '');
                
                // 显示图片预览
                updateImagePreview('card_front_preview', data.card_front);
                updateImagePreview('card_back_preview', data.card_back);
                
                $('#authenticationModal').modal('show');
            } else {
                layer.msg(res.msg, {icon: 2, time: 2000});
            }
        });
    }

    // 更新图片预览
    function updateImagePreview(elementId, imageUrl) {
        var previewDiv = $('#' + elementId);
        previewDiv.empty();
        if (imageUrl) {
            previewDiv.html('<img src="' + imageUrl + '" style="max-width: 200px; max-height: 150px;" class="img-thumbnail">');
        }
    }

    // 监听文件上传变化
    $('#auth_card_front_file').on('change', function() {
        previewLocalImage(this, 'card_front_preview');
    });

    $('#auth_card_back_file').on('change', function() {
        previewLocalImage(this, 'card_back_preview');
    });

    // 本地预览图片
    function previewLocalImage(input, previewId) {
        var file = input.files[0];
        if (file) {
            // 检查文件类型
            if (!file.type.match('image.*')) {
                layer.msg('请选择图片文件', {icon: 2, time: 2000});
                return;
            }
            
            // 检查文件大小（限制为5MB）
            if (file.size > 5 * 1024 * 1024) {
                layer.msg('图片大小不能超过5MB', {icon: 2, time: 2000});
                return;
            }

            // 显示预览
            var reader = new FileReader();
            reader.onload = function(e) {
                updateImagePreview(previewId, e.target.result);
            };
            reader.readAsDataURL(file);
        }
    }

    // 提交实名认证表单
    $(document).ready(function() {
        $('#authenticationForm').on('submit', function(e) {
            e.preventDefault();
            
            // 使用FormData来提交文件
            var formData = new FormData(this);
            
            $.ajax({
                url: '<?php echo url("admin/User/updateUserAuth"); ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.code == 200) {
                        layer.msg('更新成功', {icon: 1, time: 2000}, function() {
                            $('#authenticationModal').modal('hide');
                            window.location.reload();
                        });
                    } else {
                        layer.msg(response.msg, {icon: 2, time: 2000});
                    }
                },
                error: function() {
                    layer.msg('更新失败，请重试', {icon: 2, time: 2000});
                }
            });
        });
    });
</script>
<style>
    th{
        font-size: 14px;
    }
</style>

        </section>
    </div>
</div>
<script>
    var url = "<?php echo $url;?>";
    $('a[href="'+url+'"]').parent().addClass('active');
    var headerTitle = $('a[href="'+url+'"]').children('span').text();
    $('#content-header-title').text(headerTitle);

    function updatePassword()
    {
        var url = "<?php echo url('admin/AdminUser/showUpdatePassword'); ?>";
        layer.open({
            type: 2,
            title: '修改密码',
            area: ['60%', '45%'], //宽高
            content: url
        });
    }

    //显示单张图片
    function seePhoto(imgUrl)
    {
        var data = [];
        var temp = {
            alt: imgUrl,
            pid: imgUrl,
            src: imgUrl,
            thumb: imgUrl
        };
        data.push(temp);
        var photos = {
            'title': "查看大图", //相册标题
            'id': 1, //相册id
            'start': 0, //初始显示的图片序号，默认0
            'data': data
        };
        layer.photos({
            photos: photos,
            anim: 5,
            closeBtn: true
        });
    }
</script>
</body>
</html>

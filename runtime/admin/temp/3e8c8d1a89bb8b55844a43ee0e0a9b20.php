<?php /*a:4:{s:93:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/exclusive_log/exclusive_log_list.html";i:1754294218;s:67:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/layout.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/head.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/left.html";i:1754294218;}*/ ?>
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
        <input type="text" value="<?php echo isset($req['phone']) ? htmlentities($req['phone']) : ''; ?>" name="phone" placeholder="搜索用户手机号" class="form-control">
        <select name="exclusive_setting_id" class="form-control">
            <option value="">--全部补贴类型--</option>
            <option <?php if (isset($req['exclusive_setting_id']) && $req['exclusive_setting_id'] == 1) { echo 'selected = "selected"'; } ?> value="1">专属补贴类型1</option>
            <option <?php if (isset($req['exclusive_setting_id']) && $req['exclusive_setting_id'] == 2) { echo 'selected = "selected"'; } ?> value="2">专属补贴类型2</option>
            <option <?php if (isset($req['exclusive_setting_id']) && $req['exclusive_setting_id'] == 3) { echo 'selected = "selected"'; } ?> value="3">专属补贴类型3</option>
            <option <?php if (isset($req['exclusive_setting_id']) && $req['exclusive_setting_id'] == 4) { echo 'selected = "selected"'; } ?> value="4">专属补贴类型4</option>
            <option <?php if (isset($req['exclusive_setting_id']) && $req['exclusive_setting_id'] == 5) { echo 'selected = "selected"'; } ?> value="5">专属补贴类型5</option>
        </select>
        <select name="status" class="form-control">
            <option value="">--全部状态--</option>
            <option <?php if (isset($req['status']) && $req['status'] == 0) {
                echo 'selected = "selected"';
            } ?> value="0">处理中</option>
            <option <?php if (isset($req['status']) && $req['status'] == 1) {
                echo 'selected = "selected"';
            } ?> value="1">已完成</option>
            <option <?php if (isset($req['status']) && $req['status'] == 2) {
                echo 'selected = "selected"';
            } ?> value="2">拒绝</option>
        </select>
        <input placeholder="开始时间" autocomplete="off" value="<?php echo isset($req['start_date']) ? htmlentities($req['start_date']) : ''; ?>" name="start_date" class="form-control layer-date" id="start">
        ～
        <input placeholder="结束时间" autocomplete="off" value="<?php echo isset($req['end_date']) ? htmlentities($req['end_date']) : ''; ?>" name="end_date" class="form-control layer-date" id="end">
        <input class="btn btn-flat btn-primary m_10" type="submit" value="搜索">
    </form>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">专属补贴申领记录</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-bordered table-hover table-striped">
                    <tbody>
                    <tr>
                        <th>ID</th>
                        <th>用户ID</th>
                        <th>用户手机号</th>
                        <th>用户姓名</th>
                        <th>补贴类型</th>
                        <th>民生金</th>
                        <th>状态</th>
                        <th>申请时间</th>
                        <th>操作</th>
                    </tr>
                                            <?php foreach ($data as $k => $v) { ?>
                            <tr>
                                <td><?php echo htmlentities($v['id']); ?></td>
                            <td><?php echo htmlentities($v['user_id']); ?></td>
                            <td><?php echo htmlentities($v['phone']); ?></td>
                            <td><?php echo htmlentities($v['realname']); ?></td>
                            <td><?php echo htmlentities($v['setting_name']); ?></td>
                            <td><?php echo htmlentities($v['minsheng_amount']); ?></td>
                            <td>
                                <?php if ($v['status'] == 0) { ?>
                                    <span class="label label-warning">处理中</span>
                                <?php } elseif ($v['status'] == 1) { ?>
                                    <span class="label label-success">已完成</span>
                                <?php } else { ?>
                                    <span class="label label-danger">拒绝</span>
                                <?php } ?>
                            </td>
                            <td><?php echo htmlentities($v['creat_time']); ?></td>
                            <td>
                                <?php if ($v['status'] == 0) { ?>
                                    <a <?php echo auth_show_judge('ExclusiveLog/auditPass'); ?> class="btn btn-flat btn-success btn-xs" href="javascript:;" onclick="auditPass(<?php echo htmlentities($v['id']); ?>)">
                                        <i class="fa fa-check"></i> 审核通过
                                    </a>
                                    <a <?php echo auth_show_judge('ExclusiveLog/auditReject'); ?> class="btn btn-flat btn-danger btn-xs" href="javascript:;" onclick="auditReject(<?php echo htmlentities($v['id']); ?>)">
                                        <i class="fa fa-times"></i> 审核拒绝
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted">已审核</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="box-footer clearfix">
                <div style="text-align:center;font-size: 14px;"><?php echo $data->render(); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- 审核通过弹框 -->
<div class="modal fade" id="passModal" tabindex="-1" role="dialog" aria-labelledby="passModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="passModalLabel">审核通过</h4>
            </div>
            <form id="passForm">
                <div class="modal-body">
                    <input type="hidden" id="pass_id" name="id">
                    <div class="form-group">
                        <label for="pass_minsheng_amount">民生金金额</label>
                        <input type="number" class="form-control" id="pass_minsheng_amount" name="minsheng_amount" step="0.01" min="0.01" placeholder="请输入民生金金额" required>
                        <small class="form-text text-muted">请输入要补贴给用户的民生金金额</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-success">确认通过</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 拒绝原因弹框 -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="rejectModalLabel">拒绝原因</h4>
            </div>
            <form id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" id="reject_id" name="id">
                    <div class="form-group">
                        <label for="reject_reason">拒绝原因</label>
                        <textarea class="form-control" id="reject_reason" name="reason" rows="3" placeholder="请输入拒绝原因" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-danger">确认拒绝</button>
                </div>
            </form>
        </div>
    </div>
</div>



<script>
// 审核通过
function auditPass(id) {
    $('#pass_id').val(id);
    $('#pass_minsheng_amount').val('');
    $('#passModal').modal('show');
}

// 审核拒绝
function auditReject(id) {
    $('#reject_id').val(id);
    $('#reject_reason').val('');
    $('#rejectModal').modal('show');
}

// 审核通过表单提交
$('#passForm').submit(function (e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.post('<?php echo url("admin/ExclusiveLog/auditPass"); ?>', formData, function (res) {
        if (res.code == 200) {
            $('#passModal').modal('hide');
            layer.msg('审核成功', {icon: 1, time: 2000}, function () {
                location.reload();
            });
        } else {
            layer.msg(res.msg, {icon: 5, time: 3000});
        }
    });
});

// 拒绝表单提交
$('#rejectForm').submit(function (e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.post('<?php echo url("admin/ExclusiveLog/auditReject"); ?>', formData, function (res) {
        if (res.code == 200) {
            $('#rejectModal').modal('hide');
            layer.msg('拒绝成功', {icon: 1, time: 2000}, function () {
                location.reload();
            });
        } else {
            layer.msg(res.msg, {icon: 5, time: 3000});
        }
    });
});
</script>

<style>
.search {
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f5f5f5;
    border-radius: 5px;
}

.search .form-control {
    margin-right: 10px;
    margin-bottom: 10px;
}

.box-header h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.label-warning {
    background-color: #f0ad4e;
}

.label-success {
    background-color: #5cb85c;
}

.label-danger {
    background-color: #d9534f;
}

.btn-xs {
    margin-right: 5px;
}

.table th {
    background-color: #f5f5f5;
    font-weight: bold;
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

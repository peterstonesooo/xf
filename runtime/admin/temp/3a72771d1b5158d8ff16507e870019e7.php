<?php /*a:4:{s:80:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/user/authentication.html";i:1754294219;s:67:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/layout.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/head.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/left.html";i:1754294218;}*/ ?>
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
        <!-- <input type="text" value="<?php echo isset($req['up_user']) ? htmlentities($req['up_user']) : ''; ?>" name="up_user" placeholder="搜索上级用户ID/手机号"
               class="form-control"> -->
        <input type="text" value="<?php echo isset($req['phone']) ? htmlentities($req['phone']) : ''; ?>" name="phone" placeholder="搜索手机号" class="form-control">
        <input type="text" value="<?php echo isset($req['realname']) ? htmlentities($req['realname']) : ''; ?>" name="realname" placeholder="搜索实名姓名" class="form-control">
        <!-- <select name="level" class="form-control">
            <option value="">搜索等级</option>
            <?php foreach (config('map.user')['level_map'] as $k => $v) { ?>
                <option <?php if (isset($req['level']) && $req['level'] !== '' && $req['level'] == $k) {
                    echo 'selected = "selected"';
                } ?> value="<?php echo htmlentities($k); ?>"><?php echo htmlentities($v); ?>
                </option>
            <?php } ?>
        </select> -->
        <select name="status" class="form-control">
            <option value="">搜索状态</option>
            <option <?php if (isset($req['status']) && $req['status'] !== '' && $req['status'] == 0) {
                echo 'selected = "selected"';
            } ?> value="0">待审核
            </option>
            <option <?php if (isset($req['status']) && $req['status'] !== '' && $req['status'] == 1) {
                echo 'selected = "selected"';
            } ?> value="1">审核通过
            </option>
            <option <?php if (isset($req['status']) && $req['status'] !== '' && $req['status'] == 2) {
                echo 'selected = "selected"';
            } ?> value="2">审核拒绝
            </option>
        </select>
        <?php if(isset($req['user_id']) && $data->isEmpty()){ ?>
        <a  class="btn btn-flat btn-success m_10 f_r"
            href="<?php echo url('admin/User/showUserNum',['user_id' => $req['user_id']]); ?>"> <i class="fa fa-plus m-r-10"></i>添 加</a>
        <?php } ?>
        <button type="submit" class="btn btn-flat btn-primary">搜索</button>
        <div class="btn-group m_10_l_0">
            <button type="button" class="btn btn-flat btn-success" onclick="batchPass()">批量通过</button>
            <button type="button" class="btn btn-flat btn-danger" onclick="batchReject()">批量拒绝</button>
            <button type="button" class="btn btn-flat btn-danger" onclick="batchDelete()">批量删除</button>
        </div>
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
                            <th><input type="checkbox" id="checkAll"></th>
                            <th>ID</th>
                            <th>姓名</th>
                            <th>性别</th>
                            <th>手机号</th>
                            <th>身份证正面</th>
                            <th>身份证反面</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>审核时间</th>
                            <th>操作</th>
                        </tr>
                        <?php foreach ($data as $k => $v) { ?>
                            <tr>
                                <td><input type="checkbox" class="checkItem" value="<?php echo htmlentities($v['id']); ?>"></td>
                                <td><?php echo htmlentities($v['id']); ?></td>
                                <td><?php echo htmlentities($v['realname']); ?></td>
                                <td><?php echo htmlentities($v['gender']); ?></td>
                                <td><?php echo isset($v['phone']) ? htmlentities($v['phone']) : ''; ?></td>
                                <td><a href="<?php echo htmlentities($v['card_front']); ?>" target="_blank"><img src="<?php echo htmlentities($v['card_front']); ?>" alt="" width="50" height="50"></a></td>
                                <td><a href="<?php echo htmlentities($v['card_back']); ?>" target="_blank"><img src="<?php echo htmlentities($v['card_back']); ?>" alt="" width="50" height="50"></a></td>
                                <td><?php if ($v['status'] == 0) {?>
                                    <span class="label label-warning">待审核</span>
                                    <?php }elseif($v['status'] == 1){ ?>
                                    <span class="label label-success">已通过</span>
                                    <?php }elseif($v['status'] == 2){ ?>
                                    <span class="label label-danger">已拒绝</span>
                                    <?php if(!empty($v['remark'])){ ?>
                                    <br><small class="text-muted">原因：<?php echo htmlentities($v['remark']); ?></small>
                                    <?php } }?>
                                </td>
                                <td><?php echo htmlentities($v['created_at']); ?></td>
                                <td><?php echo htmlentities($v['checked_at']); ?></td>
                                <td>
                                    <?php if ($v['status'] == 0) {?>
                                    <button <?php echo auth_show_judge('User/pass'); ?> class="btn btn-flat btn-success btn-xs" href="<?php echo url('admin/User/pass', ['id' => $v['id']]); ?>" onclick="pass(<?php echo htmlentities($v['id']); ?>)">审核通过</button>
                                    <button <?php echo auth_show_judge('User/reject'); ?> class="btn btn-flat btn-danger btn-xs" href="<?php echo url('admin/User/reject', ['id' => $v['id']]); ?>" onclick="reject(<?php echo htmlentities($v['id']); ?>)">审核拒绝</button>
                                    <?php }?>
                                    <button <?php echo auth_show_judge('User/deleteAuth'); ?> class="btn btn-flat btn-danger btn-xs" onclick="deleteAuth(<?php echo htmlentities($v['id']); ?>)">删除</button>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align:center;font-size: 14px;">
                    <div class="pull-left" style="margin: 20px 0;">
                        <select class="form-control" id="pageSize" onchange="changePageSize(this.value)">
                            <option value="10" <?php if(isset($req['limit']) && $req['limit'] == 10) echo 'selected'; ?>>10条/页</option>
                            <option value="50" <?php if(isset($req['limit']) && $req['limit'] == 50) echo 'selected'; ?>>50条/页</option>
                            <option value="100" <?php if(isset($req['limit']) && $req['limit'] == 100) echo 'selected'; ?>>100条/页</option>
                            <option value="200" <?php if(isset($req['limit']) && $req['limit'] == 200) echo 'selected'; ?>>200条/页</option>
                        </select>
                    </div>
                    <?php echo $data->render(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(function(){
        // 全选/取消全选
        $("#checkAll").click(function(){
            $(".checkItem").prop("checked", $(this).prop("checked"));
        });

        // 监听分页链接点击
        $('.pagination a').click(function(e) {
            // 清除所有选择
            $(".checkItem").prop("checked", false);
            $("#checkAll").prop("checked", false);
        });
    });

    // 改变每页显示数量
    function changePageSize(size) {
        var url = new URL(window.location.href);
        url.searchParams.set('limit', size);
        url.searchParams.set('page', 1); // 切换每页数量时重置到第一页
        window.location.href = url.toString();
    }

    function batchPass()
    {
        var ids = [];
        $(".checkItem:checked").each(function(){
            ids.push($(this).val());
        });
        if(ids.length == 0){
            layer.msg('请选择要审核的记录', {icon: 5, time: 2000});
            return;
        }
        layer.confirm('确定要通过选中的实名认证吗？', {
            btn: ['确定','取消']
        }, function(){
            $.post('<?php echo url("admin/User/batchPass") ?>', {ids: ids}, function (res) {
                if (res.code == '200') {
                    layer.msg('批量审核通过成功', {icon: 1, time: 2000}, function(){
                        window.location.reload();
                    });
                } else {
                    layer.msg(res.msg || '操作失败', {icon: 5, time: 3000});
                }
            });
        });
    }

    function batchReject()
    {
        var ids = [];
        $(".checkItem:checked").each(function(){
            ids.push($(this).val());
        });
        if(ids.length == 0){
            layer.msg('请选择要审核的记录', {icon: 5, time: 2000});
            return;
        }
        layer.prompt({
            formType: 2,
            title: '请输入拒绝原因',
            area: ['300px', '150px']
        }, function(reason, index){
            if(!reason.trim()) {
                layer.msg('拒绝原因不能为空', {icon: 5, time: 2000});
                return;
            }
            $.post('<?php echo url("admin/User/batchReject") ?>', {ids: ids, reason: reason}, function (res) {
                if (res.code == '200') {
                    layer.msg('批量审核拒绝成功', {icon: 1, time: 2000}, function(){
                        window.location.reload();
                    });
                } else {
                    layer.msg(res.msg || '操作失败', {icon: 5, time: 3000});
                }
            });
            layer.close(index);
        });
    }

    function batchDelete()
    {
        var ids = [];
        $(".checkItem:checked").each(function(){
            ids.push($(this).val());
        });
        if(ids.length == 0){
            layer.msg('请选择要删除的记录', {icon: 5, time: 2000});
            return;
        }
        layer.confirm('确定要删除选中的记录吗？此操作不可恢复！', {
            btn: ['确定','取消']
        }, function(){
            $.post('<?php echo url("admin/User/batchDeleteAuth") ?>', {ids: ids}, function (res) {
                if (res.code == '200') {
                    layer.msg('批量删除成功', {icon: 1, time: 2000}, function(){
                        window.location.reload();
                    });
                } else {
                    layer.msg(res.msg || '操作失败', {icon: 5, time: 3000});
                }
            });
        });
    }

    function deleteAuth(id)
    {
        layer.confirm('确定要删除该记录吗？此操作不可恢复！', {
            btn: ['确定','取消']
        }, function(){
            $.post('<?php echo url("admin/User/deleteAuth") ?>', {id: id}, function (res) {
                if (res.code == '200') {
                    layer.msg('删除成功', {icon: 1, time: 2000}, function(){
                        window.location.reload();
                    });
                } else {
                    layer.msg(res.msg || '操作失败', {icon: 5, time: 3000});
                }
            });
        });
    }

    function pass(id)
    {
        layer.confirm('确定要通过该用户的实名认证吗？', {
            btn: ['确定','取消']
        }, function(){
            $.post('<?php echo url("admin/User/pass") ?>', {id: id}, function (res) {
                if (res.code == '200') {
                    layer.msg('审核通过成功', {icon: 1, time: 2000}, function(){
                        window.location.reload();
                    });
                } else {
                    layer.msg(res.msg || '操作失败', {icon: 5, time: 3000});
                }
            });
        });
    }

    function reject(id)
    {
        layer.prompt({
            formType: 2,
            title: '请输入拒绝原因',
            area: ['300px', '150px']
        }, function(reason, index){
            if(!reason.trim()) {
                layer.msg('拒绝原因不能为空', {icon: 5, time: 2000});
                return;
            }
            $.post('<?php echo url("admin/User/reject") ?>', {id: id, reason: reason}, function (res) {
                if (res.code == '200') {
                    layer.msg('已拒绝该认证', {icon: 1, time: 2000}, function(){
                        window.location.reload();
                    });
                } else {
                    layer.msg(res.msg || '操作失败', {icon: 5, time: 3000});
                }
            });
            layer.close(index);
        });
    }

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
                if (res.code != 200) {
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

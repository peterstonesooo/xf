<?php /*a:4:{s:87:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/auth_group/auth_group_list.html";i:1754294218;s:67:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/layout.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/head.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/left.html";i:1754294218;}*/ ?>
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
        <input type="text" value="<?php echo isset($req['auth_group_id']) ? htmlentities($req['auth_group_id']) : ''; ?>" name="auth_group_id" placeholder="搜索角色ID" class="form-control">
        <input class="btn btn-flat btn-primary m_10" type="submit" value="搜索">
        <a <?php echo auth_show_judge('AuthGroup/addAuthGroup'); ?> class="btn btn-flat btn-success m_10 f_r" href="<?php echo url('admin/AuthGroup/showAuthGroup'); ?>"><i class="fa fa-plus m-r-10"></i>添加角色</a>
    </form>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">角色权限管理</h3>
                <div class="box-tools pull-right">
                    <span class="label label-info">角色总数：<?php echo htmlentities($data->total()); ?></span>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-bordered table-hover table-striped">
                    <tbody>
                    <tr>
                        <th width="80">ID</th>
                        <th width="150">角色名称</th>
                        <th>角色描述</th>
                        <th width="120">状态</th>
                        <th width="150">创建时间</th>
                        <th width="200">操作</th>
                    </tr>
                    <?php foreach ($data as $k => $v){ ?>
                        <tr>
                            <td><?php echo htmlentities($v['id']); ?></td>
                            <td><span class="label label-primary"><?php echo htmlentities($v['title']); ?></span></td>
                            <td><?php echo htmlentities($v['desc']); ?></td>
                            <td>
                                <?php if($v['status'] == 1): ?>
                                    <span class="label label-success"><?php echo htmlentities($v['status_text']); ?></span>
                                <?php else: ?>
                                    <span class="label label-danger"><?php echo htmlentities($v['status_text']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlentities($v['created_at']); ?></td>
                            <td>
                                <a <?php echo auth_show_judge('AuthGroup/editAuthGroup'); ?> class="btn btn-flat btn-info btn-xs" href="<?php echo url('admin/AuthGroup/showAuthGroup', ['id' => $v['id']]); ?>"><i class="fa fa-edit"></i> 编辑</a>
                                <a <?php echo auth_show_judge('AuthGroup/submitAssignAuth'); ?> class="btn btn-flat btn-success btn-xs" onclick="showAssignAuth(<?php echo htmlentities($v['id']); ?>)"><i class="fa fa-hand-rock-o"></i> 权限配置</a>
                                <a <?php echo auth_show_judge('AuthGroup/delAuthGroup'); ?> class="btn btn-flat btn-danger btn-xs" href="javascript:;" onclick="delAuthGroup(<?php echo htmlentities($v['id']); ?>)"><i class="fa fa-trash-o"></i> 删除</a>
                            </td>
                        </tr>
                    <?php }?>
                    </tbody>
                </table>
            </div>
            <div class="box-footer clearfix">
                <div style="text-align:center;font-size: 14px;"><?php echo $data->render();?></div>
            </div>
        </div>
    </div>
</div>

<style>
    .box-header h3 {
        margin: 0;
        font-size: 18px;
        color: #333;
    }
    .label-info {
        background-color: #5bc0de;
    }
    .label-success {
        background-color: #5cb85c;
    }
    .label-danger {
        background-color: #d9534f;
    }
    .label-primary {
        background-color: #337ab7;
    }
    .table th {
        background-color: #f5f5f5;
        font-weight: bold;
    }
    .btn-xs {
        margin-right: 5px;
    }
</style>

<script>
    function delAuthGroup(id)
    {
        layer.confirm('确定删除这个角色吗？删除后不可恢复！', {icon: 3, title:'提示'}, function(index) {
            layer.close(index);
            $.post('<?php echo url("admin/AuthGroup/delAuthGroup"); ?>', {id:id}, function (res) {
                if (res.code == '200'){
                    layer.msg('删除成功', {icon: 1, time: 2000}, function(){
                        location.reload(true);
                    });
                }
                else {
                    layer.msg(res.msg, {icon: 5, time: 3000});
                }
            });
        });
    }

    function changeAuthGroup(id, field)
    {
        var value = 0;
        if($('#'+field+id).is(':checked')) {
            value = 0;
        }
        else{
            value = 1;
        }

        layer.confirm('确定操作吗', {icon: 3, title:'提示'}, function(index) {
            layer.close(index);
            $.post('<?php echo url("admin/AuthGroup/changeAuthGroup"); ?>', {"id": id, "value": value, "field": field}, function (res) {
                if (res.code != 200) {
                    if(value == 1){
                        $('#'+field+id).prop('checked', false);
                    }
                    else {
                        $('#'+field+id).prop('checked', true);
                    }
                    layer.msg(res.msg, {icon: 5, time: 2500, offset: '80px'});
                }
            });
        }, function(index2) {
            if(value == 1){
                $('#'+field+id).prop('checked', false);
            }
            else {
                $('#'+field+id).prop('checked', true);
            }
        });
    }

    function showAssignAuth(id)
    {
        var url = "<?php echo url('admin/AuthGroup/showAssignAuth'); ?>" + "?auth_group_id=" + id;

        layer.open({
            type: 2,
            title: '分配权限',
            area: ['60%', '85%'],
            content: url,
            shadeClose: false
        });
    }
</script>

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

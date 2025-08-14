<?php /*a:4:{s:75:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/user/show_user.html";i:1754675488;s:67:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/layout.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/head.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/left.html";i:1754294218;}*/ ?>
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
        <a class="btn btn-flat btn-primary m_10_l_0" href="<?php echo url('admin/User/userList'); ?>">显示全部</a>
        <a class="btn btn-flat btn-success m_10 f_r" onclick="javascript:history.back(-1)"><i
                    class="fa fa-undo m-r-10"></i>返 回</a>
    </form>
</div>

<div class="row">
    <div class="col-sm-12 col-xs-12">
        <form id="commentForm">
            <?php if (!empty($data)) { ?>
                <input type="hidden" class="form-control" value="<?php echo htmlentities($data['id']); ?>" name="id">
            <?php } ?>
            <div class="box box-body">
                <div class="row dd_input_group">
                    <div class="form-group">
                        <label class="col-xs-4 col-sm-2 col-md-2 col-lg-1 control-label dd_input_l">手机号</label>
                        <div class="col-xs-7 col-sm-6 col-md-4 col-lg-4">
                            <input  type="text" name="phone" class="form-control" placeholder="请输入手机号"
                                   value="<?php echo isset($data['phone']) ? htmlentities($data['phone']) : ''; ?>">
                        </div>
                        <div class="col-xs-1 col-sm-4 col-md-6 col-lg-6 dd_ts">*</div>
                    </div>
                </div>
                <div class="row dd_input_group">
                    <div class="form-group">
                        <label class="col-xs-4 col-sm-2 col-md-2 col-lg-1 control-label dd_input_l">实名认证姓名</label>
                        <div class="col-xs-7 col-sm-6 col-md-4 col-lg-4">
                            <input type="text" name="realname" class="form-control" placeholder="请输入实名认证姓名"
                                   value="<?php echo isset($data['realname']) ? htmlentities($data['realname']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <!-- <div class="row dd_input_group">
                    <div class="form-group">
                        <label class="col-xs-4 col-sm-2 col-md-2 col-lg-1 control-label dd_input_l">身份证号</label>
                        <div class="col-xs-7 col-sm-6 col-md-4 col-lg-4">
                            <input type="text" name="ic_number" class="form-control" placeholder="请输入身份证号"
                                   value="<?php echo isset($data['ic_number']) ? htmlentities($data['ic_number']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="row dd_input_group">
                    <div class="form-group">
                        <label class="col-xs-4 col-sm-2 col-md-2 col-lg-1 control-label dd_input_l">登录密码</label>
                        <div class="col-xs-7 col-sm-6 col-md-4 col-lg-4">
                            <input type="text" name="password" class="form-control" placeholder="输入登录密码，就代表重置登录密码"
                                   value="">
                        </div>
                    </div>
                </div>
                <div class="row dd_input_group">
                    <div class="form-group">
                        <label class="col-xs-4 col-sm-2 col-md-2 col-lg-1 control-label dd_input_l">支付密码</label>
                        <div class="col-xs-7 col-sm-6 col-md-4 col-lg-4">
                            <input type="text" name="pay_password" class="form-control" placeholder="输入支付密码，就代表重置支付密码"
                                   value="">
                        </div>
                    </div>
                </div> -->
                <div class="row dd_input_group">
                    <div class="form-group">
                        <div class="col-xs-12 col-sm-8 col-md-6 col-lg-5 text-center">
                            <button type="button" class="btn btn-flat btn-primary" onclick="sub()">提 交</button>
                            <button type="button" class="btn btn-flat btn-default"
                                    onclick="javascript:history.back(-1)">返 回
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    $(function () {
        var url = "<?php echo url('admin/User/userList'); ?>";
        $('a[href="' + url + '"]').parents('.menu-li').addClass('active');
        var headerText = $('a[href="' + url + '"]').children('span').text();
        var is_add = "<?php echo !empty($data) ? '0' : '1';?>";
        var headerTitle = is_add == "1" ? headerText + '-添加' : headerText + '-编辑';
        $('#content-header-title').text(headerTitle);
    });

    function sub()
    {
        $.post('<?php echo !empty($data) ? url("admin/User/editUser") : url("admin/User/addUser");?>', $('#commentForm').serialize(), function (res) {
            if (res.code == '200') {
                window.location.href = document.referrer;
            } else {
                layer.msg(res.msg, {icon: 5, time: 3000});
            }
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

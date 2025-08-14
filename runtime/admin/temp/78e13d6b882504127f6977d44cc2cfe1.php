<?php /*a:4:{s:81:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/project/project_list.html";i:1754294218;s:67:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/layout.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/head.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/left.html";i:1754294218;}*/ ?>
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
        <input type="text" value="<?php echo isset($req['project_id']) ? htmlentities($req['project_id']) : ''; ?>" name="project_id" placeholder="搜索项目ID" class="form-control">
        <select name="project_group_id"  class="form-control">
            <option value="">搜索项目分组</option>
            <?php foreach($groups as $key=>$v): ?>)}
            <option value="<?php echo htmlentities($key); ?>" <?php if((isset($data['project_group_id']) && $data['project_group_id']==$key)): ?> selected <?php endif; ?>><?php echo htmlentities($v); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" value="<?php echo isset($req['name']) ? htmlentities($req['name']) : ''; ?>" name="name" placeholder="搜索项目名称" class="form-control">
        <select name="status" class="form-control">
            <option value="">搜索启用状态</option>
            <?php foreach (config('map.project')['status_map'] as $k => $v) { ?>
                <option <?php if (isset($req['status']) && $req['status'] != '' && $req['status'] == $k) {
                    echo 'selected = "selected"';
                } ?> value="<?php echo htmlentities($k); ?>"><?php echo htmlentities($v); ?>
                </option>
            <?php } ?>
        </select>
        <select name="is_recommend" class="form-control">
            <option value="">搜索是否推荐</option>
            <?php foreach (config('map.project')['is_recommend_map'] as $k => $v) { ?>
                <option <?php if (isset($req['is_recommend']) && $req['is_recommend'] != '' && $req['is_recommend'] == $k) {
                    echo 'selected = "selected"';
                } ?> value="<?php echo htmlentities($k); ?>"><?php echo htmlentities($v); ?>
                </option>
            <?php } ?>
        </select>
        <select name="class" class="form-control">
            <option value="">搜索项目期数</option>
            <option value="1" <?php if (isset($req['class']) && $req['class'] == 1) { echo 'selected = "selected"'; } ?>>一期</option>
            <option value="2" <?php if (isset($req['class']) && $req['class'] == 2) { echo 'selected = "selected"'; } ?>>二期</option>
            <option value="3" <?php if (isset($req['class']) && $req['class'] == 3) { echo 'selected = "selected"'; } ?>>三期</option>
            <option value="4" <?php if (isset($req['class']) && $req['class'] == 4) { echo 'selected = "selected"'; } ?>>四期</option>
            <option value="5" <?php if (isset($req['class']) && $req['class'] == 5) { echo 'selected = "selected"'; } ?>>五期</option>
            <option value="6" <?php if (isset($req['class']) && $req['class'] == 6) { echo 'selected = "selected"'; } ?>>六期</option>
            <option value="7" <?php if (isset($req['class']) && $req['class'] == 7) { echo 'selected = "selected"'; } ?>>七期</option>
            <option value="8" <?php if (isset($req['class']) && $req['class'] == 8) { echo 'selected = "selected"'; } ?>>八期</option>
            <option value="9" <?php if (isset($req['class']) && $req['class'] == 9) { echo 'selected = "selected"'; } ?>>九期</option>
            <option value="10" <?php if (isset($req['class']) && $req['class'] == 10) { echo 'selected = "selected"'; } ?>>十期</option>
        </select>
        <input class="btn btn-flat btn-primary m_10" type="submit" value="搜索">
        <a <?php echo auth_show_judge('Project/addProject'); ?> class="btn btn-flat btn-success m_10 f_r"
        href="<?php echo url('admin/Project/showProject'); ?>"><i class="fa fa-plus m-r-10"></i>添 加</a>
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
                            <th>分组ID</th>
                            <th>封面图</th>
                            <th>项目名称</th>
                            <th>申购金额</th>
                            <th>惠民金</th>
                            <th>共富金</th>
                            <th>返佣比例</th>
                            <th>返利方式</th>
                            <th>申购周期</th>
                            <th>是否启用</th>
                            <!-- <th>是否推荐</th> -->
                            <!-- <th>支付方式</th> -->
                            <th>工作日份额数</th>
                            <th>周末份额数</th>
                            <th>项目期数</th>
                            <!-- <th>排序号</th> -->
                            <!-- <th>创建时间</th> -->
                            <th>操作</th>
                        </tr>
                        <?php foreach ($data as $k => $v) { ?>
                            <tr>
                                <td><?php echo htmlentities($v['id']); ?></td>
                                <td><?php echo htmlentities($groups[$v['project_group_id']]); ?></td>
                                <td><img src="<?php echo htmlentities($v['cover_img']); ?>" onclick="seePhoto('<?php echo htmlentities($v['cover_img']); ?>')" style="max-width: 80px;"></td>
                                <td><?php echo htmlentities($v['name']); ?></td>
                                <td><?php echo htmlentities($v['single_amount']); ?></td>
                                <td><?php echo htmlentities($v['huimin_amount']); ?></td>
                                <td><?php echo htmlentities($v['gongfu_amount']); ?></td>
                                <td><?php echo htmlentities($v['rebate_rate']); ?></td>
                                <td><?php echo isset($v['daily_bonus_ratio']) && $v['daily_bonus_ratio'] > 0 ? '每日返利' : '到期返利'; ?></td>
                                <td><?php echo htmlentities($v['period']); ?></td>
                                <td>
                                    <div class="switch">
                                        <div class="onoffswitch">
                                            <input type="checkbox" <?php echo $v['status'] == 1 ? 'checked' : ''; ?>
                                                   class="onoffswitch-checkbox" id="status<?php echo htmlentities($v['id']); ?>">
                                            <label class="onoffswitch-label" for="status<?php echo htmlentities($v['id']); ?>"
                                                   onclick="changeProject(<?php echo htmlentities($v['id']); ?>, 'status')">
                                                <span class="onoffswitch-inner"></span>
                                                <span class="onoffswitch-switch"></span>
                                            </label>
                                        </div>
                                    </div>
                                </td>
                                <!-- <td>
                                    <div class="switch">
                                        <div class="onoffswitch">
                                            <input type="checkbox" <?php echo $v['is_recommend'] == 1 ? 'checked' : ''; ?>
                                                   class="onoffswitch-checkbox" id="is_recommend<?php echo htmlentities($v['id']); ?>">
                                            <label class="onoffswitch-label" for="is_recommend<?php echo htmlentities($v['id']); ?>"
                                                   onclick="changeProject(<?php echo htmlentities($v['id']); ?>, 'is_recommend')">
                                                <span class="onoffswitch-inner"></span>
                                                <span class="onoffswitch-switch"></span>
                                            </label>
                                        </div>
                                    </div>
                                </td> -->
                                <!-- <td><?php echo htmlentities($v['support_pay_methods_text']); ?></td> -->
                                <!-- <td>工作日<?php echo htmlentities($v['total_quota']); ?></br>周末<?php echo htmlentities($v['remaining_quota']); ?></td> -->
                                <td><?php echo htmlentities($v['total_quota']); ?></td>
                                <td><?php echo htmlentities($v['remaining_quota']); ?></td>
                                <td><?php switch($v['class']){
                                    case 1:
                                        echo '一期';
                                        break;
                                    case 2:
                                        echo '二期';
                                        break;
                                    case 3:
                                        echo '三期';
                                        break;
                                    case 4:
                                        echo '四期';
                                        break;
                                    case 5:
                                        echo '五期';
                                        break;
                                    case 6:
                                        echo '六期';
                                        break;
                                    case 7:
                                        echo '七期';
                                        break;
                                    case 8:
                                        echo '八期';
                                        break;
                                    case 9:
                                        echo '九期';
                                        break;
                                    case 10:
                                        echo '十期';
                                        break;
                                } ?></td>
                                <!-- <td><?php echo htmlentities($v['sort']); ?></td> -->
                                <!-- <td><?php echo htmlentities($v['created_at']); ?></td> -->
                                <td>
                                    <a <?php echo auth_show_judge('Project/editProject'); ?> class="btn btn-flat btn-info btn-xs"
                                    href="<?php echo url('admin/Project/showProject', ['id' => $v['id']]); ?>"><i
                                            class="fa fa-edit"></i> 编辑</a>
                                    <a <?php echo auth_show_judge('Project/delProject'); ?> class="btn btn-flat btn-danger btn-xs"
                                    href="javascript:;" onclick="delProject(<?php echo htmlentities($v['id']); ?>)"><i class="fa fa-trash-o"></i>
                                    删除</a>
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

<script>
    function delProject(id)
    {
        //确认框
        layer.confirm('确定删除吗？', {icon: 3, title: '提示'}, function (index) {
            layer.close(index);
            $.post('<?php echo url("admin/Project/delProject"); ?>', {id: id}, function (res) {
                if (res.code == '200') {
                    location.reload(true);
                } else {
                    layer.msg(res.msg, {icon: 5, time: 3000});
                }
            });
        });
    }

    function changeProject(id, field)
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
            $.post('<?php echo url("admin/Project/changeProject"); ?>', {"id": id, "value": value, "field": field}, function (res) {
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

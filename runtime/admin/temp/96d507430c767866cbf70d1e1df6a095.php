<?php /*a:4:{s:79:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/capital/topup_list.html";i:1754294218;s:67:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/layout.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/head.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/left.html";i:1754294218;}*/ ?>
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
        <input type="text" value="<?php echo isset($req['capital_id']) ? htmlentities($req['capital_id']) : ''; ?>" name="capital_id" placeholder="搜索ID" class="form-control">
        <input type="text" value="<?php echo isset($req['user']) ? htmlentities($req['user']) : ''; ?>" name="user" placeholder="搜索用户ID/手机号" class="form-control">
        <input type="text" value="<?php echo isset($req['capital_sn']) ? htmlentities($req['capital_sn']) : ''; ?>" name="capital_sn" placeholder="搜索单号" class="form-control">
        <select name="status" class="form-control">
            <option value="">搜索状态</option>
            <?php foreach (config('map.capital')['topup_status_map'] as $k => $v) { ?>
                <option <?php if (isset($req['status']) && $req['status'] == $k) {
                    echo 'selected = "selected"';
                } ?> value="<?php echo htmlentities($k); ?>"><?php echo htmlentities($v); ?>
                </option>
            <?php } ?>
        </select>
        <select name="pay_channel" class="form-control">
            <option value="">搜索支付方式</option>
            <?php foreach (config('map.capital')['pay_channel_map'] as $k => $v) { if (in_array($k, [2,3,4,5])) { ?>
                <option <?php if (isset($req['pay_channel']) && $req['pay_channel'] == $k) {
                    echo 'selected = "selected"';
                } ?> value="<?php echo htmlentities($k); ?>"><?php echo htmlentities($v); ?>
                </option>
            <?php } } ?>
        </select>
        <select name="channel" class="form-control">
            <option value="">搜索支付渠道</option>
            <?php foreach (config('map.payment_config')['channel_map'] as $k => $v) { if ($k > 0) { ?>
                <option <?php if (isset($req['channel']) && $req['channel'] == $k) {
                    echo 'selected = "selected"';
                } ?> value="<?php echo htmlentities($k); ?>"><?php echo htmlentities($v); ?>
                </option>
            <?php } } ?>
        </select>
        <input type="text" value="<?php echo isset($req['mark']) ? htmlentities($req['mark']) : ''; ?>" name="mark" placeholder="搜索渠道标识"
               class="form-control">
        <input placeholder="开始时间" autocomplete="off" value="<?php echo isset($req['start_date']) ? htmlentities($req['start_date']) : ''; ?>" name="start_date" class="form-control layer-date" id="start">
        ～
        <input placeholder="结束时间" autocomplete="off" value="<?php echo isset($req['end_date']) ? htmlentities($req['end_date']) : ''; ?>" name="end_date" class="form-control layer-date" id="end">
        <input class="btn btn-flat btn-primary m_10" type="submit" value="搜索">
        <input class="btn btn-flat btn-info m_10" name="export" type="submit" value="导出">
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
                            <th>用户</th>
                            <th>单号</th>
                            <th>充值类型</th>
                            <th>充值状态</th>
                            <th>支付状态</th>
                            <th>支付渠道</th>
                            <th>收款卡信息</th>
                            <th>支付凭证</th>
                            <th>充值金额</th>
                            <th>支付时间</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                        <?php foreach ($data as $k => $v) { ?>
                            <tr>
                                <td><?php echo htmlentities($v['id']); ?></td>
                                <td><?php echo isset($v['user']['realname']) ? htmlentities($v['user']['realname']) : ''; ?>（<?php echo isset($v['user']['phone']) ? htmlentities($v['user']['phone']) : ''; ?>）</td>
                                <td><?php echo htmlentities($v['capital_sn']); ?></td>
                                <td><?php echo htmlentities($v['topup_type_text']); ?></td>
                                <td><?php echo htmlentities($v['topup_status_text']); ?></td>
                                <td><?php echo htmlentities($v['topup_pay_status_text']); ?></td>
                                <td><?php echo htmlentities($v['pay_channel_text']); ?> (<?php echo htmlentities($v['chanel_text']); ?>)-<?php echo htmlentities($v['pay_type']); ?></td>
                                <td><?php if ($v['pay_channel'] == 5) { ?> 银行：<?php echo isset($v['payment']['card_info']['bank_name']) ? htmlentities($v['payment']['card_info']['bank_name']) : ''; ?><br>卡号：<?php echo isset($v['payment']['card_info']['card_number']) ? htmlentities($v['payment']['card_info']['card_number']) : ''; ?><br>分行：<?php echo isset($v['payment']['card_info']['bank_branch']) ? htmlentities($v['payment']['card_info']['bank_branch']) : ''; ?><br>持卡人：<?php echo isset($v['payment']['card_info']['realname']) ? htmlentities($v['payment']['card_info']['realname']) : ''; } ?></td>
                                <td><?php if (!empty($v['payment']['pay_voucher_img_url'])) { ?><img src="<?php echo htmlentities($v['payment']['pay_voucher_img_url']); ?>" onclick="seePhoto('<?php echo htmlentities($v["payment"]["pay_voucher_img_url"]); ?>')" style="max-width: 80px;"> <?php } ?></td>
                                <td><?php echo htmlentities($v['amount']); ?></td>
                                <td><?php echo htmlentities($v['audit_date']); ?></td>
                                <td><?php echo htmlentities($v['created_at']); ?></td>
                                <td>
                                    <?php if ($v['status'] == 1) { ?>
                                        <button <?php echo auth_show_judge('Capital/auditTopup'); ?> class="btn btn-flat btn-success btn-xs" onclick="auditCapital(<?php echo htmlentities($v['id']); ?>, 2)">确认支付成功</button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } if($auth_check == '超级管理员') {?>
                        <tr>
                            <td><strong>总计：</strong></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td><strong><?php echo htmlentities($total_amount); ?></strong></td>
                            <td></td>
                            <td></td>
                            <td></td>
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
        laydate.render(start);
        laydate.render(end);
    });

    function auditCapital(id, status)
    {
        layer.confirm('确定操作吗？', {icon: 3, title: '提示'}, function (index) {
            layer.close(index);
            $.post('<?php echo url("admin/Capital/auditTopup"); ?>', {id: id, status: status}, function (res) {
                if (res.code == '200') {
                    location.reload(true);
                } else {
                    layer.msg(res.msg, {icon: 5, time: 3000});
                }
            });
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

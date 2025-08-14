<?php /*a:4:{s:85:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/setting/data_correction1.html";i:1754363908;s:67:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/layout.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/head.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/left.html";i:1754294218;}*/ ?>
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
        <input type="text" value="<?php echo isset($req['user_id']) ? htmlentities($req['user_id']) : ''; ?>" name="user_id" placeholder="用户ID" class="form-control">
        <select name="log_type" class="form-control">
            <option value="">搜索钱包类型</option>
            <option value="3" <?php if (isset($req['log_type']) && $req['log_type'] == 3) echo 'selected = "selected"'; ?>>补贴钱包</option>
            <option value="4" <?php if (isset($req['log_type']) && $req['log_type'] == 4) echo 'selected = "selected"'; ?>>数字钱包</option>
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
                <h3 class="box-title">购买商品每周分红数据列表</h3>
                <div class="box-tools">
                    <button type="button" class="btn btn-danger btn-sm" onclick="batchFixRecords()">一键修复所有</button>
                    <span class="label label-info">总记录数：<?php echo htmlentities($list->total()); ?></span>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-bordered table-hover table-striped">
                    <tbody>
                    <tr>
                        <th>ID</th>
                        <th>用户ID</th>
                        <th>钱包类型</th>
                        <th>关联ID</th>
                        <th>变动前余额</th>
                        <th>变动金额</th>
                        <th>变动后余额</th>
                        <th>备注</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                    <?php if (!empty($list)) { foreach ($list as $item) { ?>
                            <tr>
                                <td><?php echo htmlentities($item['id']); ?></td>
                                <td><?php echo htmlentities($item['user_id']); ?></td>
                                <td>
                                    <?php 
                                    $logTypeMap = [
                                        3 => '补贴钱包',
                                        4 => '数字钱包'
                                    ];
                                    echo isset($logTypeMap[$item['log_type']]) ? $logTypeMap[$item['log_type']] : $item['log_type'];
                                    ?>
                                </td>
                                <td><?php echo htmlentities($item['relation_id']); ?></td>
                                <td><?php echo htmlentities($item['before_balance']); ?></td>
                                <td class="<?php echo $item['change_balance'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $item['change_balance'] > 0 ? '+' : ''; ?><?php echo htmlentities($item['change_balance']); ?>
                                </td>
                                <td><?php echo htmlentities($item['after_balance']); ?></td>
                                <td><?php echo htmlentities($item['remark']); ?></td>
                                <td><?php echo htmlentities($item['created_at']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-xs btn-info" onclick="viewDetail(<?php echo htmlentities($item['id']); ?>)">查看详情</button>
                                    <?php if ($item['remark'] == '购买商品每周分红') { ?>
                                        <button type="button" class="btn btn-xs btn-warning" onclick="fixRecord(<?php echo htmlentities($item['id']); ?>)">修复操作</button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } } else { ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">暂无数据</td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($list) && $list->hasPages()) { ?>
                <div style="text-align:center;font-size: 14px;"><?php echo $list->render(); ?></div>
            <?php } ?>
        </div>
    </div>
</div>

<!-- 详情模态框 -->
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">记录详情</h4>
            </div>
            <div class="modal-body">
                <div id="detailContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">关闭</button>
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

    function viewDetail(id) {
        // 这里可以通过AJAX获取详细信息
        // 暂时显示基本信息
        var row = $('tr').filter(function() {
            return $(this).find('td:first').text() == id;
        });
        
        if (row.length > 0) {
            var cells = row.find('td');
            var html = '<table class="table table-bordered">';
            html += '<tr><th>字段</th><th>值</th></tr>';
            html += '<tr><td>ID</td><td>' + $(cells[0]).text() + '</td></tr>';
            html += '<tr><td>用户ID</td><td>' + $(cells[1]).text() + '</td></tr>';
            html += '<tr><td>钱包类型</td><td>' + $(cells[2]).text() + '</td></tr>';
            html += '<tr><td>关联ID</td><td>' + $(cells[3]).text() + '</td></tr>';
            html += '<tr><td>变动前余额</td><td>' + $(cells[4]).text() + '</td></tr>';
            html += '<tr><td>变动金额</td><td>' + $(cells[5]).text() + '</td></tr>';
            html += '<tr><td>变动后余额</td><td>' + $(cells[6]).text() + '</td></tr>';
            html += '<tr><td>备注</td><td>' + $(cells[7]).text() + '</td></tr>';
            html += '<tr><td>创建时间</td><td>' + $(cells[8]).text() + '</td></tr>';
            html += '</table>';
            
            $('#detailContent').html(html);
            $('#detailModal').modal('show');
        }
    }

    function fixRecord(id) {
        if (!confirm('确定要修复这条记录吗？此操作将：\n1. 修改备注为"购买商品每周分红-已修复"\n2. 从原钱包扣除相应金额\n3. 将金额转入对应的正确钱包\n\n此操作不可撤销！')) {
            return;
        }
        
        $.ajax({
            url: '<?php echo url("fixDataCorrection1"); ?>',
            type: 'POST',
            data: {
                id: id
            },
            success: function(res) {
                if (res.code == 0) {
                    alert('修复成功！');
                    location.reload();
                } else {
                    alert('修复失败：' + res.msg);
                }
            },
            error: function() {
                alert('网络错误，请重试');
            }
        });
    }

    function batchFixRecords() {
        if (!confirm('确定要一键修复所有记录吗？\n\n此操作将：\n1. 修复所有备注为"购买商品每周分红"的记录\n2. 修改备注为"购买商品每周分红-已修复"\n3. 从digit_balance扣除相应金额\n4. 将金额转入对应的正确钱包\n\n此操作不可撤销，且可能需要较长时间！')) {
            return;
        }
        
        // 显示加载提示
        var loadingMsg = '正在批量修复，请稍候...';
        var loadingDiv = $('<div id="loadingDiv" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 5px; z-index: 9999;">' + loadingMsg + '</div>');
        $('body').append(loadingDiv);
        
        $.ajax({
            url: '<?php echo url("batchFixDataCorrection1"); ?>',
            type: 'POST',
            data: {},
            success: function(res) {
                $('#loadingDiv').remove();
                if (res.code == 0) {
                    alert(res.msg);
                    location.reload();
                } else {
                    alert('批量修复失败：' + res.msg);
                }
            },
            error: function() {
                $('#loadingDiv').remove();
                alert('网络错误，请重试');
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

<?php /*a:4:{s:84:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/setting/data_correction.html";i:1754294218;s:67:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/layout.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/head.html";i:1754294218;s:65:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/left.html";i:1754294218;}*/ ?>
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
        <select name="type" class="form-control">
            <option value="">搜索类型</option>
            <?php foreach (config('map.user_balance_log')['balance_type_map'] as $k => $v) { ?>
                <option <?php if (isset($req['type']) && $req['type'] == $k) {
                    echo 'selected = "selected"';
                } ?> value="<?php echo htmlentities($k); ?>"><?php echo htmlentities($v); ?>
                </option>
            <?php } ?>
        </select>
        <select name="log_type" class="form-control">
            <option value="">搜索钱包类型</option>
            <?php foreach (config('map.user_balance_log')['log_type_map'] as $k => $v) { ?>
                <option <?php if (isset($req['log_type']) && $req['log_type'] == $k) {
                    echo 'selected = "selected"';
                } ?> value="<?php echo htmlentities($k); ?>"><?php echo htmlentities($v); ?>
                </option>
            <?php } ?>
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
                <h3 class="box-title">数据修正 - 用户余额日志重复数据（type在[59,67,68,60,64]范围内）</h3>
                <div class="box-tools">
                    <button type="button" class="btn btn-danger btn-sm" onclick="batchDelete()">批量删除重复数据</button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-bordered table-hover table-striped">
                    <tbody>
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        </th>
                        <th>用户ID</th>
                        <th>类型</th>
                        <th>钱包类型</th>
                        <th>关联ID</th>
                        <th>变动金额</th>
                        <th>日期</th>
                        <th>重复数量</th>
                        <th>操作</th>
                    </tr>
                    <?php if (!empty($data)) { foreach ($data as $k => $v) { ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="group-checkbox" value="<?php echo htmlentities($v['user_id']); ?>_<?php echo htmlentities($v['type']); ?>_<?php echo htmlentities($v['log_type']); ?>_<?php echo htmlentities($v['relation_id']); ?>_<?php echo htmlentities($v['change_balance']); ?>_<?php echo htmlentities($v['date']); ?>">
                                </td>
                                <td><?php echo htmlentities($v['user_id']); ?></td>
                                <td>
                                    <?php 
                                    $typeMap = config('map.user_balance_log.balance_type_map');
                                    echo isset($typeMap[$v['type']]) ? $typeMap[$v['type']] : $v['type'];
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $logTypeMap = config('map.user_balance_log.log_type_map');
                                    echo isset($logTypeMap[$v['log_type']]) ? $logTypeMap[$v['log_type']] : $v['log_type'];
                                    ?>
                                </td>
                                <td><?php echo htmlentities($v['relation_id']); ?></td>
                                <td><?php echo htmlentities($v['change_balance']); ?></td>
                                <td><?php echo htmlentities($v['date']); ?></td>
                                <td><span class="label label-danger"><?php echo htmlentities($v['duplicate_count']); ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-xs btn-warning" onclick="viewDetails('<?php echo htmlentities($v['user_id']); ?>', '<?php echo htmlentities($v['type']); ?>', '<?php echo htmlentities($v['log_type']); ?>', '<?php echo htmlentities($v['relation_id']); ?>', '<?php echo htmlentities($v['change_balance']); ?>', '<?php echo htmlentities($v['date']); ?>')">查看详情</button>
                                    <button type="button" class="btn btn-xs btn-danger" onclick="deleteDuplicate('<?php echo htmlentities($v['user_id']); ?>', '<?php echo htmlentities($v['type']); ?>', '<?php echo htmlentities($v['log_type']); ?>', '<?php echo htmlentities($v['relation_id']); ?>', '<?php echo htmlentities($v['change_balance']); ?>', '<?php echo htmlentities($v['date']); ?>')">删除重复</button>
                                </td>
                            </tr>
                        <?php } } else { ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">暂无重复数据</td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($data)) { ?>
                <div style="text-align:center;font-size: 14px;padding: 10px;">
                    <h5>共找到 <?php echo count($data); ?> 组重复数据</h5>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<!-- 详情模态框 -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">重复数据详情</h4>
            </div>
            <div class="modal-body">
                <div id="detailsContent"></div>
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

    function toggleSelectAll() {
        var checked = $('#selectAll').prop('checked');
        $('.group-checkbox').prop('checked', checked);
    }

    function viewDetails(userId, type, logType, relationId, changeBalance, date) {
        // 获取当前页面的数据
        var details = <?php echo json_encode($data ?? []); ?>;
        var targetGroup = null;
        
        for (var i = 0; i < details.length; i++) {
            var item = details[i];
            if (item.user_id == userId && item.type == type && item.log_type == logType && 
                item.relation_id == relationId && item.change_balance == changeBalance && item.date == date) {
                targetGroup = item;
                break;
            }
        }
        
        if (targetGroup && targetGroup.details) {
            var html = '<table class="table table-bordered">';
            html += '<tr><th>ID</th><th>用户ID</th><th>类型</th><th>钱包类型</th><th>关联ID</th><th>变动前余额</th><th>变动金额</th><th>变动后余额</th><th>备注</th><th>创建时间</th></tr>';
            
            for (var i = 0; i < targetGroup.details.length; i++) {
                var detail = targetGroup.details[i];
                html += '<tr>';
                html += '<td>' + (detail.id || '') + '</td>';
                html += '<td>' + (detail.user_id || '') + '</td>';
                html += '<td>' + (detail.type || '') + '</td>';
                html += '<td>' + (detail.log_type || '') + '</td>';
                html += '<td>' + (detail.relation_id || '') + '</td>';
                html += '<td>' + (detail.before_balance || '') + '</td>';
                html += '<td>' + (detail.change_balance || '') + '</td>';
                html += '<td>' + (detail.after_balance || '') + '</td>';
                html += '<td>' + (detail.remark || '') + '</td>';
                html += '<td>' + (detail.created_at || '') + '</td>';
                html += '</tr>';
            }
            html += '</table>';
            
            $('#detailsContent').html(html);
            $('#detailsModal').modal('show');
        } else {
            alert('未找到详细信息');
        }
    }

    function deleteDuplicate(userId, type, logType, relationId, changeBalance, date) {
        if (!confirm('确定要删除这组重复数据吗？将保留第一条记录，删除其他重复记录。')) {
            return;
        }
        
        $.ajax({
            url: '<?php echo url("deleteDuplicateData"); ?>',
            type: 'POST',
            data: {
                user_id: userId,
                type: type,
                log_type: logType,
                relation_id: relationId,
                change_balance: changeBalance,
                date: date
            },
            success: function(res) {
                if (res.code == 0) {
                    alert(res.msg);
                    location.reload();
                } else {
                    alert(res.msg);
                }
            },
            error: function() {
                alert('操作失败，请重试');
            }
        });
    }

    function batchDelete() {
        var selectedGroups = [];
        $('.group-checkbox:checked').each(function() {
            var values = $(this).val().split('_');
            selectedGroups.push({
                user_id: values[0],
                type: values[1],
                log_type: values[2],
                relation_id: values[3],
                change_balance: values[4],
                date: values[5]
            });
        });
        
        if (selectedGroups.length == 0) {
            alert('请选择要删除的重复数据组');
            return;
        }
        
        if (!confirm('确定要批量删除选中的 ' + selectedGroups.length + ' 组重复数据吗？')) {
            return;
        }
        
        $.ajax({
            url: '<?php echo url("batchDeleteDuplicateData"); ?>',
            type: 'POST',
            data: {
                ids: selectedGroups
            },
            success: function(res) {
                if (res.code == 0) {
                    alert(res.msg);
                    location.reload();
                } else {
                    alert(res.msg);
                }
            },
            error: function() {
                alert('操作失败，请重试');
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

<?php /*a:1:{s:73:"/Applications/XAMPP/xamppfiles/htdocs/xf/app/admin/view/common/login.html";i:1754294218;}*/ ?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo config('app.app_name'); ?>管理系统</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <link rel="stylesheet" href="/admin/plugins/layui/css/layui.css">
    <link rel="stylesheet" href="/admin/css/login.css?v=1.1">

    <script src="/admin/bower_components/jquery/dist/jquery.min.js"></script>
</head>

<body class="signin">
    <div class="layadmin-user-login layadmin-user-display-show" id="LAY-user-login">
        <div class="layadmin-user-login-main">
            <div class="layadmin-user-login-box layadmin-user-login-header">
                <h2><?php echo config('app.app_name'); ?>管理系统</h2>
            </div>
            <div class="layadmin-user-login-box layadmin-user-login-body layui-form">
                <form id="commentForm" method="post">
                    <div class="layui-form-item">
                        <label class="layadmin-user-login-icon layui-icon layui-icon-username" for="LAY-user-login-username"></label>
                        <input type="text" name="account" id="LAY-user-login-username" placeholder="用户名" class="layui-input">
                    </div>
                    <div class="layui-form-item">
                        <label class="layadmin-user-login-icon layui-icon layui-icon-password" for="LAY-user-login-password"></label>
                        <input type="password" name="password" id="LAY-user-login-password" placeholder="密码" class="layui-input">
                    </div>
                    <div class="layui-form-item">
                        <button class="layui-btn layui-btn-fluid login" type="button" onclick="sub()">登 录</button>
                    </div>
                    <div class="layui-trans layadmin-user-login-footer">
                        <p><?php echo config('app.app_name'); ?>管理系统 Copyright © 2022</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        $(document).keyup(function(event){
            if(event.keyCode == 13){
                $(".login").trigger("click");
            }
        });

        function sub()
        {
            $.post("<?php echo url('admin/Common/login'); ?>", $('#commentForm').serialize(), function (res) {
                if (res.code == '200'){
                    if (res.data.isValid == 0) {
                        window.location.href = "<?php echo url('admin/Home/index'); ?>"
                    }
                    else {
                        window.location.href = "<?php echo url('admin/Common/secondaryValidation'); ?>"
                        //window.location.href = "<?php echo url('admin/Home/index'); ?>"
                    }
                }
                else {
                    alert(res.msg);
                }
            });
        }
    </script>
</body>
</html>

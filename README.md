# 部署文档
- php7.3 + nginx1.21 + mysql5.7
- git clone 代码
- 在项目根目录执行：
```
    composer install
```
- 再项目根目录执行以下语句：
```
    sudo chmod -R 777 runtime && sudo chmod -R 777 public/storage
```
- 创建数据库，然后执行根目录下的init.sql文件
- 在根目录下创建 .env 文件，然后把.example.env文件里面的内容复制进去，然后把里面的配置要改下，注意改数据库的配置和域名的配置，如下：
```
    [DATABASE]下面的：HOSTNAME，DATABASE，USERNAME，PASSWORD，HOSTPORT（数据库相关配置）
    [APP]下面的：HOST （api的根域名）
    [COMMON]下面的：callback_url （前端根域名）
```
- 添加crontab定时任务(注意前面的路径改成线上真实路径)：
```
  1 0 * * * cd /home/wwwroot/mip_sys && php think checkBonus
  0 0 * * * cd /home/wwwroot/mip_sys && php think sendCashReward
```
- nginx配置，注意要把二级域名是api和eadmin：
```
server {
  listen       80;

  server_name  api.xxx.com eadmin.xxx.com;

  root         /根目录/public/;

  index        index.php index.html index.htm;

  location / {
    try_files $uri $uri/ /index.php?s=$uri&$args;
  }

  location ~ index.php$
  {
    fastcgi_pass 127.0.0.1:9000;
    include fastcgi.conf;
  }
}

```


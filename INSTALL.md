# ZComposer 镜像的安装部署

```sh
$ apt install beanstalkd
$ cd composer-mirror
$ composer install
```

## 常用命令

```sh
# 执行抓取任务
$ php ./bin/console app:crawler

# 后台多进程模型同步又拍云
$ php ./bin/console app:daemon

# 清理过期垃圾文件
$ php ./bin/console app:clear --expired=json

# 扫描并校验所有json和zip文件的hash256
$ php ./bin/console app:scan
```

## 修改配置参数

> 通常根据自己部署的实际环境，修改参数。详细配置说明详见 config.default.php

`cp config.default.php config.php`，修改 config.php 中的如下参

```php
    /**
     * distdir 用于存储 zip 包
     */
    'distdir' => __DIR__ . '/dist/',

    /**
     * 指向 mirrorUrl 对应的 web 实际目录
     */
    'cachedir' => __DIR__ . '/cache/',

    /**
     * packagistUrl：官方采集源
     */
    'packagistUrl' => 'https://packagist.org',

    /**
     * 镜像包发布站点, packages.json 入口根域名
     */
    'mirrorUrl' => 'https://packagist.laravel-china.org',

    /**
     * .json 中 dist 分发 zip 包的CDN域名
     */
    'distUrl' => 'https://dl.laravel-china.org/',
```

### supervisor 配置

`sudo vim /etc/supervisor/supervisord.conf`，添加如下配置信息：

    [program:crawler]
    command=php ./bin/console app:crawler
    directory=/home/zencodex/composer-mirror/  ;部署代码的位置，自行替换
    autostart=true
    autorestart=true
    redirect_stderr = true  ; 把 stderr 重定向到 stdout，默认 false
    stdout_logfile_maxbytes = 10MB  ; stdout 日志文件大小，默认 50MB
    stdout_logfile_backups = 5      ; stdout 日志文件备份数
    stdout_logfile = /tmp/composer_crawler_stdout.log
    
    [program:composer_daemon]
    command=php ./bin/console app:daemon
    directory=/home/zencodex/composer-mirror/  ;部署代码的位置，自行替换
    autostart=true
    autorestart=true
    redirect_stderr = true  ; 把 stderr 重定向到 stdout，默认 false
    stdout_logfile_maxbytes = 10MB  ; stdout 日志文件大小，默认 50MB
    stdout_logfile_backups = 5      ; stdout 日志文件备份数
    stdout_logfile = /tmp/composer_daemon_stdout.log

### crontab 定时任务

```
# sudo crontab -e
# 根据自己环境代码的位置，替换 /home/zencodex/composer-mirror 
# getcomposer 是获取最新的 composer，上传到 CDN 云存储

0 */2 * * * /usr/bin/php /home/zencodex/composer-mirror/bin/console app:clear --expired=json
0 1 * * * /usr/bin/php /home/zencodex/composer-mirror/getcomposer.php
```

### 注意最大子目录数的坑

代码详情见 `src/Commands/PatchCommand.php`

```php
/*
|--------------------------------------------------------------------------
| linux ext4 支持的最大子目录数有上限，大约 64000 ~ 65000，目前包的数量已经超过上限
|--------------------------------------------------------------------------
|
| 有三种解决方法，前2种基本不现实。所以自己通过尝试，找到了3 (软连接不计数的方案)
|
|   1. 更换没有子文件夹数量限制的文件系统，比如 xfs 
|   2. 或者更改相关代码，重新编译 ext4 内核
|   3. 切割大的文件夹，分散不同字母开头的文件。在主文件夹里面使用软连接，软连接并不计数
|
*/
```
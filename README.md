packagist-crawler
========================

Requirement
------------------
- PHP >= 5.6
- ext-curl
- ext-hash
- ext-json
- ext-zlib
- ext-PDO
- ext-pdo\_sqlite

Install
------------------

```sh
$ apt install beanstalkd
$ cd packagist-crawler
$ composer install
```

How to use
------------------

```sh

# 执行抓取任务
$ php ./crawler.php

# 后台多进程模型同步又拍云
$ php daemon.php

# 清理过期垃圾文件
$ php clearexpired.php

# 检测所有 json文件的 hash256
$ php ./checkhash.php

```


Configuration
------------------

- config.default.php
- config.php

copy config.default.php to config.php

supervisor config:

    [program:crawler]
    command=/usr/bin/php /home/zencodex/packagist-crawler/crawer.php
    directory=/home/zencodex/packagist-crawler/
    autostart=true
    autorestart=true
    redirect_stderr = true  ; 把 stderr 重定向到 stdout，默认 false
    stdout_logfile_maxbytes = 10MB  ; stdout 日志文件大小，默认 50MB
    stdout_logfile_backups = 5      ; stdout 日志文件备份数
    stdout_logfile = /tmp/composer_crawler_stdout.log
    
    [program:composer_daemon]
    command=/usr/bin/php /home/zencodex/packagist-crawler/deaemon.php
    directory=/home/zencodex/precache/
    autostart=true
    autorestart=true
    redirect_stderr = true  ; 把 stderr 重定向到 stdout，默认 false
    stdout_logfile_maxbytes = 10MB  ; stdout 日志文件大小，默认 50MB
    stdout_logfile_backups = 5      ; stdout 日志文件备份数
    stdout_logfile = /tmp/composer_daemon_stdout.log
    
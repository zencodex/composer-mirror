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
$ cd packagist-crawler
$ composer install
```

How to use
------------------

```sh

# 执行抓取任务
$ php ./crawler.php

# 检测所有 json文件的 hash256
$ php ./checkhash

```


Configuration
------------------

- config.default.php
- config.php

copy config.default.php to config.php

supervisor config:

    [program:crawler]
    ; command=/usr/bin/php /home/zencodex/precache/artisan composer:crawler
    ; directory=/home/zencodex/precache/
    command=/usr/bin/php /home/zencodex/packagist-crawler/crawer.php
    directory=/home/zencodex/packagist-crawler/
    autostart=true
    autorestart=true
    redirect_stderr = true  ; 把 stderr 重定向到 stdout，默认 false
    stdout_logfile_maxbytes = 10MB  ; stdout 日志文件大小，默认 50MB
    stdout_logfile_backups = 10     ; stdout 日志文件备份数
    stdout_logfile = /tmp/composer_crawler_stdout.log
    
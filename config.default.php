<?php

/**
 * copy config.default.php to config.php
 */

return (object)[

    /**
     * distdir 用于存储 zip 包
     */
    'distdir' => __DIR__ . '/dist/',

    /**
     * 指向 mirrorUrl 对应的 web 实际目录
     */
    'cachedir' => __DIR__ . '/cache/',

    /**
     *  程序运行过程中，生成的一些存储文件，比如都已经采集过哪些，做为下次增量的判断依据
     */
    'dbdir' => __DIR__ . '/db/',

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

    /**
     * 是否同步到云，本地测试采集时，可先设置为 false
     */
    'cloudsync' => true,

    /**
     * guzzle 采集时的超时时间
     */
    'timeout' => 600,

    /**
     * 清理过期包时，判断过期的间隔时间，单位分钟
     */
    'expireMinutes' => 20,

    /**
     * 最大并发数，越大采集效率越高
     */
    'maxConnections' => 500,

    'upyun' => (object)[
        'operator' => 'composer',
        'password' => '',
        'bucket' => (object)[
            'json' => 'mirror-json',
            'zip' => 'mirror-dist',
        ],
    ],

    /**
     * isPrefetch：早期建立初始数据源时，利用又拍云的直接下载文件任务接口，可忽略
     */
    'isPrefetch' => false,
];

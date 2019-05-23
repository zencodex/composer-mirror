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
     * cloudDisk: 注意是 object，不是 array，内部的参数是 array
     * 使用 Flyststem Adapter, 第三方云存储的封装类
     * 
     * 使用又拍云: 
     * 'adapter' => 'ZenCodex\\Support\\Flysystem\\Adapter\\UpyunAdapter';
     * 先安装扩展包: composer require zencodex/flysystem-upyun
     * 
     * 使用七牛云: 
     * 'adapter' => 'Overtrue\\Flysystem\\Qiniu\\QiniuAdapter';
     * 先安装扩展包: composer require overtrue/flysystem-qiniu
     * 
     * 测试状态，可以使用空适配器:
     * 'adapter' => 'League\\Flysystem\\Adapter\\NullAdapter';
     * 
     * 其余的可这里查找: <https://github.com/thephpleague/flysystem>
     */ 
    'cloudDisk' => (object)[
        'adapter' => 'ZenCodex\\Support\\Flysystem\\Adapter\\UpyunAdapter',
        'config' => [
            'operator' => 'composer',
            'password' => '',
            'bucket' => '',
        ],
        'bucketMap' => [
            'json' => 'mirror-json',
            'zip' => 'mirror-dist',
        ]
    ],

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

    /**
     * isPrefetch：早期建立初始数据源时，利用又拍云的直接下载文件任务接口，可忽略
     */
    'isPrefetch' => false,
];

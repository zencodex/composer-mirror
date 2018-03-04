<?php

/**
 * copy config.default.php to config.php
 */

return (object)[
    'distdir' => __DIR__ . '/dist/',
    'cachedir' => __DIR__ . '/cache/',
    //'cachedir' => '/usr/share/nginx/html/',
    //'cachedir' => '/usr/local/apache2/htdocs/',
    'packagistUrl' => 'https://packagist.org',
    'mirrorUrl' => 'https://packagist.laravel-china.org',
    'lockfile' => __DIR__ . '/cache/.lock',
    'expiredDb' => __DIR__ . '/cache/.expired.db',
    'maxConnections' => 200,
    'generateGz' => true,
    'expireMinutes' => 5 * 60,
    'url' => 'http://localhost',

    'cloudsync' => false,
    'timeout' => 6000,   // guzzle timeout
    'upyun' => (object)[
        'operator' => 'composer',
        'password' => 'VXvgE59ooJ',
        'bucket' => (object)[
            'json' => 'mirror-json',
            'zip' => 'mirror-dist',
        ],
    ],
    'distUrl' => 'https://dl.laravel-china.org/',
];

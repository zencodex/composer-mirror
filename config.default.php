<?php

/**
 * copy config.default.php to config.php
 */

return (object)[
    'distdir' => __DIR__ . '/dist/',
    'cachedir' => __DIR__ . '/cache/',
    'packagistUrl' => 'https://packagist.org',
    'mirrorUrl' => 'https://packagist.laravel-china.org',
    'distUrl' => 'https://dl.laravel-china.org/',

    'maxConnections' => 500,
    'expireMinutes' => 5 * 60,
    'url' => 'http://localhost',

    'cloudsync' => false,
    'isPrefetch' => false,
    'timeout' => 6000,   // guzzle timeout
    'upyun' => (object)[
        'operator' => 'composer',
        'password' => '',
        'bucket' => (object)[
            'json' => 'mirror-json',
            'zip' => 'mirror-dist',
        ],
    ],
];

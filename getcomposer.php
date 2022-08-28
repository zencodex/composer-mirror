<?php

use Upyun\Config;
use Upyun\Upyun;

require __DIR__ . '/vendor/autoload.php';
const BASE_URL = 'https://getcomposer.org';

date_default_timezone_set('PRC');
set_time_limit(900);

if (file_exists(__DIR__ . '/config.php')) {
    $config = require __DIR__ . '/config.php';
} else {
    $config = require __DIR__ . '/config.default.php';
}

$versions = file_get_contents(BASE_URL . '/versions');
$versions = json_decode($versions, true);
$path = $versions['stable'][0]['path'];
echo "已解析版本路径： $path \n";

if (isset($path)) {
    // https://getcomposer.org/download/2.4.1/composer.phar
    $downUrl = BASE_URL . $path;
    $writeData = file_get_contents($downUrl);
    echo "$downUrl => 准备上传到CDN \n";

    $bucketConfig = new Config(
        $config->upyun->bucket->zip,
        $config->upyun->operator,
        $config->upyun->password
    );

    $client = new Upyun($bucketConfig);
    try {
        $client->write('/composer.phar', $writeData);
        $client->purge('https://dl.laravel-china.org/composer.phar');
        echo "composer.phar成功上传到CDN \n";
    } catch (Exception $e) {
        echo $e->getMessage();
    }
} else {
    echo "错误：无法获取版本元数据 \n";
}


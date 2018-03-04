<?php

/**
 * Author: ZenCodex <v@yinqisen.cn>
 * Date: 2/28/18
 * Time: 11:49 AM
 */

use zencodex\PackagistCrawler\Cloud;

require_once __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/config.php')) {
    $config = require __DIR__ . '/config.php';
} else {
    $config = require __DIR__ . '/config.default.php';
}

$cloud = new Cloud($config);
$cloud->badCountOfAllPackages();

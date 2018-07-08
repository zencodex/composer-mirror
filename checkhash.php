<?php

/**
 * Author: ZenCodex <v@yinqisen.cn>
 * Date: 2/28/18
 * Time: 11:49 AM
 */

use zencodex\PackagistCrawler\FileUtils;
require_once __DIR__ . '/src/lib/init.php';

$fileUtils = new FileUtils($config);
$fileUtils->badCountOfAllPackages();

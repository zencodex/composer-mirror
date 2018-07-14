<?php

/**
 * Author: ZenCodex <v@yinqisen.cn>
 * Date: 2/28/18
 * Time: 11:49 AM
 */

use ProgressBar\Manager as ProgressBarManager;
use zencodex\PackagistCrawler\Cloud;
use zencodex\PackagistCrawler\ExpiredFileManager;
use zencodex\PackagistCrawler\Log;
use Symfony\Component\Finder\Finder;

require_once __DIR__ . '/src/lib/init.php';

$config->cloudsync or Log::warn('NOTE: WOULD NOT SYNC TO CLOUD');

$cloud = new Cloud($config);
$cloud->clearCloudDistFiles();


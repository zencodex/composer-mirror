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

set_time_limit(0);
ini_set('memory_limit', '1G');

require_once __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/config.php')) {
    $config = require __DIR__ . '/config.php';
} else {
    $config = require __DIR__ . '/config.default.php';
}

$config->cloudsync or Log::warn('NOTE: WOULD NOT SYNC TO CLOUD');
$expiredManager = new ExpiredFileManager($config->expiredDb, $config->expireMinutes);

$signal_handler = function ($signal) use(&$expiredManager) {
    Log::warn("kill signal, please wait ...");
    unset($expiredManager);
};

pcntl_signal(SIGINT, $signal_handler);  // Ctrl + C
pcntl_signal(SIGCHLD, $signal_handler);
pcntl_signal(SIGTSTP, $signal_handler);  // Ctrl + Z

clearExpiredFiles($expiredManager);

function clearExpiredFiles(ExpiredFileManager $expiredManager)
{
    global $config;
    $cloud = new Cloud($config);
    $expiredFiles = $expiredManager->getExpiredFileList();

    $progressBar = new ProgressBarManager(0, count($expiredFiles));
    $progressBar->setFormat("   - Clearing Expired Files: %current%/%max% [%bar%] %percent%%");

    foreach ($expiredFiles as $file) {
        while (file_exists($file)) {

            if (strpos($file, 'provider-') > 0) {
                break;
            }

            $originContent = file_get_contents($file);
            $packageData = json_decode($originContent, true);
            isset($packageData['packages']) or die('json error =>' . $file);

            foreach ($packageData['packages'] as $packageName => $versions) {
                foreach ($versions as $verNumber => $vMeta) {
                    $zipFile = $config->distdir . $packageName . '/' . $vMeta['dist']['reference'] . '.zip';
                    if (file_exists($zipFile)) {
                        // remove remote zip file
//                        $cloud->removeRemoteFile($zipFile);
                    } else {
                        Log::error(__FUNCTION__ . " => cannot find $zipFile");
                    }
                }
            }
            break;
        }

        $expiredManager->delete($file);
        unlink($file);
        // remove remote json file
        if ($config->cloudsync) {
            $cloud->removeRemoteFile($file);
        }
        $progressBar->advance();
    }
}

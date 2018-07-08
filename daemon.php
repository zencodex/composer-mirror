<?php

/**
 * Author: ZenCodex <v@yinqisen.cn>
 * Date: 2/28/18
 * Time: 11:49 AM
 */

use Pheanstalk\Pheanstalk;
use QXS\WorkerPool\ClosureWorker;
use QXS\WorkerPool\WorkerPool;
use zencodex\PackagistCrawler\Cloud;
use zencodex\PackagistCrawler\FileUtils;
use zencodex\PackagistCrawler\Log;

require_once __DIR__ . '/src/lib/init.php';

// 约定大约配置，直接传入调用的方法 和 参数
$beanstalk = new Pheanstalk('127.0.0.1');
$beanstalk->watch('composer');

$cloud = new Cloud($config);
$wp = new WorkerPool();
$wp->setWorkerPoolSize(10)->create(new ClosureWorker (

    function ($input, $semaphone, $storage) use (&$beanstalk, $cloud) {
        $cloud->{$input->method}($input->data);
    }
));

//        $isExit = false;
//        $signal_handler = function ($signal) use(&$isExit) {
//            $this->warn("kill signal, please wait for all works done");
//            $isExit = true;
//        };
//
//        pcntl_signal(SIGINT, $signal_handler);  // Ctrl + C
//        pcntl_signal(SIGCHLD, $signal_handler);
//        pcntl_signal(SIGTSTP, $signal_handler);  // Ctrl + Z

$stats = $beanstalk->stats();
Log::info('current-jobs-ready => ' . $stats['current-jobs-ready']);
Log::info('current-jobs-reserved => ' . $stats['current-jobs-reserved']);
Log::info('current-jobs-buried => ' . $stats['current-jobs-buried']);

while (1) {

    $job = $beanstalk->reserve(15); // Block until job is available.
    if (!$job) break;

    $jobData = json_decode($job->getData());
    if (!method_exists($cloud, $jobData->method)) throw new RuntimeException('找不到此方法 => ' . $jobData->method);

    // 只处理最后一个 packages.json，其余的多进程处理
    if (isMainPackageFile($jobData)) {
        processMainPackageFile($job, $jobData);
    } else {
        $wp->run($jobData);
    }

    $beanstalk->delete($job);
}

Log::info("DONE!");

/**
 * 判断是否是 packages.json
 * @param $jobData
 * @return bool
 */
function isMainPackageFile($jobData)
{
    return strpos($jobData->data, '/packages.json') > 0;
}

/**
 * 特殊处理 packages.json
 */
function processMainPackageFile($job, $jobData)
{
    global $wp, $beanstalk, $cloud, $config;

    $stats = $beanstalk->stats();
    if (intval($stats['current-jobs-ready']) == 0) {
        $triedCount = 0;
        while ($triedCount < 100 && $wp->getBusyWorkers() > 0) {
            $triedCount++;
            // 等待其他上传进程全部结束
            sleep(6);
        }

        $fileUtils = new FileUtils($config);
        if ($fileUtils->badCountOfProviderPackages('packages.json') !== 0) {
            Log::error('!!! packages.json 存在错误，跳过云更新 !!!');
            return;
        }

        $result = $cloud->{$jobData->method}($jobData->data);
        // 上传 packages.json 成功
        if ($result > 0) {
            $cloud->refreshRemoteFile($config->mirrorUrl . '/packages.json');
        }
        $beanstalk->delete($job);
    }

    $beanstalk->delete($job);
}

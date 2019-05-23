<?php

/*
|--------------------------------------------------------------------------
| 推送 beanstalk 队列中的数据到 云存储
|--------------------------------------------------------------------------
*/

namespace ZenCodex\ComposerMirror\Commands;

use QXS\WorkerPool\ClosureWorker;
use QXS\WorkerPool\WorkerPool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZenCodex\ComposerMirror\App;
use ZenCodex\ComposerMirror\FileUtils;
use ZenCodex\ComposerMirror\Log;

class DaemonCommand extends Command
{
    /** @var $wp */
    private $wp;

    protected function configure()
    {
        $this
            ->setName('app:daemon')
            ->setDescription('后台任务，处理 beanstalk 中的文件');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cloud = App::getInstance()->getCloud();
        $this->wp = new WorkerPool();
        $this->wp->setWorkerPoolSize(10)->create(new ClosureWorker (

            function ($jobData, $semaphone, $storage) use ($cloud) {
                $cloud->{$jobData->method}($jobData->data);
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

        $beanstalk = App::getInstance()->getClientHandler();
        $beanstalk->watch('composer');

        $stats = $beanstalk->stats();
        Log::info('current-jobs-ready => ' . $stats['current-jobs-ready']);
        Log::info('current-jobs-reserved => ' . $stats['current-jobs-reserved']);
        Log::info('current-jobs-buried => ' . $stats['current-jobs-buried']);

        while (1) {

            $job = $beanstalk->reserve(15); // Block until job is available.
            if (!$job) break;

            $jobData = json_decode($job->getData());
            if (!method_exists($cloud, $jobData->method)) throw new \RuntimeException('找不到此方法 => ' . $jobData->method);

            // 只处理最后一个 packages.json，其余的多进程处理
            if ($this->isMainPackageFile($jobData)) {
                $this->processMainPackageFile($jobData);
            } else {
                $this->wp->run($jobData);
            }

            try {
                $beanstalk->delete($job);
            } catch (Exception $e) {
                // Noting to do
            }
        }

        Log::info("DONE!");
    }

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
    function processMainPackageFile($jobData)
    {
        $cloud = App::getInstance()->getCloud();
        $config = App::getInstance()->getConfig();
        $beanstalk = App::getInstance()->getClientHandler();

        $stats = $beanstalk->stats();
        if (intval($stats['current-jobs-ready']) == 0) {
            $triedCount = 0;
            while ($triedCount < 100 && $this->wp->getBusyWorkers() > 0) {
                $triedCount++;
                // 等待其他上传进程全部结束
                sleep(6);
            }

            if (FileUtils::badCountOfProviderPackages('packages.json') !== 0) {
                Log::error('!!! packages.json 存在错误，跳过云更新 !!!');
                return;
            }

            $result = $cloud->{$jobData->method}($jobData->data);
            // 上传 packages.json 成功
            if ($result > 0) {
                $cloud->refreshRemoteFile($config->mirrorUrl . '/packages.json');
            }

            $packages = json_decode(file_get_contents($config->cachedir . 'packages.json'));
            $this->generateHtml($config, $packages->update_at);
        }
    }

    /**
     * 更新说明文档
     * @param $_config
     * @param $update_at
     */
    function generateHtml($_config, $update_at)
    {
        ob_start();
        include __DIR__ . '/../../index.html.php';
        file_put_contents($_config->cachedir . '/index.html', ob_get_clean());
    }
}
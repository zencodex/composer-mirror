<?php

namespace zencodex\PackagistCrawler\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use zencodex\PackagistCrawler\App;
use zencodex\PackagistCrawler\FileUtils;
use ProgressBar\Manager as ProgressBarManager;
use zencodex\PackagistCrawler\Log;

class CrawlerCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('app:crawler')
            ->setDescription('packagist 爬虫, 将数据推送到又拍云');
    }

    /*
    |--------------------------------------------------------------------------
    | Main()
    |--------------------------------------------------------------------------
    |
    | 核心采集代码
    */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = App::getConfig();
        $config->cloudsync or Log::warn('NOTE: WOULD NOT SYNC TO CLOUD');

        // 检测并生成必要目录
        file_exists($config->cachedir) or mkdir($config->cachedir, 0777, true);
        file_exists($config->distdir) or mkdir($config->distdir, 0777, true);

        $signal_handler = function ($signal) {
            Log::warn("kill signal, please wait ...");
            App::getInstance()->terminated = 1;
        };

        pcntl_signal(SIGINT, $signal_handler);  // Ctrl + C
        pcntl_signal(SIGCHLD, $signal_handler);
        pcntl_signal(SIGTSTP, $signal_handler);  // Ctrl + Z

        // 临时 workaround
        @exec('rm -f /tmp/composer_*');

        // 初始化 producer
        $clientHandler = App::getClientHandler();
        $stats = $clientHandler->stats();
        if (intval($stats['current-jobs-ready']) > 0) {
            Log::warn('还有未完成的jobs，继续等待');
            sleep(30);
            exit();
        }

        // STEP 1
        $providers = $this->downloadProviders($config);
        if (FileUtils::badCountOfProviderPackages() !== 0) {
            throw new \RuntimeException('!!! flushFiles => packages.json.new 存在错误，跳过更新 !!!');
        }

        // STEP 2
        $jsonfiles = $this->downloadPackages($config, $providers);

        // STEP 3
        $this->downloadZipballs($config, $jsonfiles);

        // STEP 4
        $this->flushFiles($config);
        //unset($globals->expiredManager);

        Log::warn("wait for 60s....");
        sleep(60);
    }

    /**
     * packages.json & provider-xxx$xxx.json downloader
     */
    function downloadProviders($config)
    {
        $cachedir = $config->cachedir;
        $packagesCache = $cachedir . 'packages.json';

        $packages = json_decode($this->request($config->packagistUrl . '/packages.json'));
        foreach (explode(' ', 'notify notify-batch search') as $k) {
            if (0 === strpos($packages->$k, '/')) {
                $packages->$k = $config->packagistUrl . $packages->$k;
            }
        }
        file_put_contents($packagesCache . '.new', json_encode($packages));

        if (empty($packages->{'provider-includes'})) {
            throw new \RuntimeException('packages.json schema changed?');
        }

        $providers = [];
        $numberOfProviders = count( (array)$packages->{'provider-includes'} );
        $progressBar = new ProgressBarManager(0, $numberOfProviders);
        $progressBar->setFormat('Downloading Providers: %current%/%max% [%bar%] %percent%%');

        $app = App::getInstance();
        foreach ($packages->{'provider-includes'} as $tpl => $version) {
            $app->terminated and exit();

            $fileurl = str_replace('%hash%', $version->sha256, $tpl);
            $cachename = $cachedir . $fileurl;
            $providers[] = $cachename;

            if (!file_exists($cachename)) {
                $data = $this->request($config->packagistUrl . '/' . $fileurl);
                if ($data) {
//                $oldcache = $cachedir . str_replace('%hash%.json', '*', $tpl);
//                if ($glob = glob($oldcache)) {
//                    foreach ($glob as $old) {
//                        $globals->expiredManager->add($old, time());
//                    }
//                }

                    FileUtils::storeFile($cachename, $data);
                    $app->getConfig()->cloudsync and $app->pushJob2Task($cachename);
                }
            } else {
                // Just update filetime
                FileUtils::touchFile($cachename, $app->timestamp);
            }

            $progressBar->advance();
        }

        return $providers;
    }

    /**
     * composer.json downloader
     *
     */
    function downloadPackages($config, $providers)
    {
        $cachedir = $config->cachedir;
        $i = 1;
        $numberOfProviders = count($providers);
        $jsonfiles = [];
        $packageObjs = [];

        $app = App::getInstance();
        foreach ($providers as $providerjson) {
            $list = json_decode(file_get_contents($providerjson));
            if (!$list || empty($list->providers)) continue;

            $list = $list->providers;
//        $all = count((array)$list);
//        $progressBar = new ProgressBarManager(0, $all);
            echo "   - Provider {$i}/{$numberOfProviders}:\n";
//        $progressBar->setFormat("      - Package: %current%/%max% [%bar%] %percent%%");

            $sum = 0;
            foreach ($list as $packageName => $provider) {
                $app->terminated and exit();

//            $progressBar->advance();
                ++$sum;
                $url = "$config->packagistUrl/p/$packageName\$$provider->sha256.json";
                $cachefile = $cachedir . str_replace("$config->packagistUrl/", '', $url);

                if (file_exists($cachefile)) {
                    FileUtils::touchFile($cachefile, $app->timestamp);
                    continue;
                }

                $packageObjs[] = (object)[
                    'packageName' => $packageName,
                    'url' => $url,
                    'sha256' => $provider->sha256,
                ];
            }

            ++$i;
        }

        // 开始下载
        $arrChuncks = array_chunk($packageObjs, $config->maxConnections);
        $progressBar = new ProgressBarManager(0, count($packageObjs));
        $progressBar->setFormat("   - New Packages: %current%/%max% [%bar%] %percent%%");
        $client = new Client([ RequestOptions::TIMEOUT => $config->timeout]);

        foreach ($arrChuncks as $chunk) {
            $requests = [];
            foreach ($chunk as $package) {
                App::getInstance()->terminated and exit();

                $req = new Request('GET', $package->url);
                $req->sha256 = $package->sha256;
                $req->packageName = $package->packageName;
                $requests[] = $req;
            }

            $pool = new Pool($client, $requests, [
                'concurrency' => $config->maxConnections,
                'fulfilled' => function ($res, $index) use (&$jsonfiles, &$requests, $progressBar) {

                    $config = App::getConfig();

                    $req = $requests[$index];
                    $cachedir = $config->cachedir;
                    $progressBar->advance();

                    if (200 !== $res->getStatusCode() || $req->sha256 !== hash('sha256', (string)$res->getBody())) {
                        Log::error( "\t sha256 wrong => ". $req->getUri());
                        return;
                    }

                    $cachefile = $cachedir . str_replace("$config->packagistUrl/", '', $req->getUri());
                    //                $cachefile2 = $cachedir . '/p/' . $req->packageName . '.json';
                    $jsonfiles[] = $cachefile;

//                if ($glob = glob("{$cachedir}p/$req->packageName\$*")) {
//                    foreach ($glob as $old) {
//                        $globals->expiredManager->add($old, time());
//                    }
//                }

                    FileUtils::storeFile($cachefile, (string)$res->getBody());
                    if ($config->cloudsync) {
                        App::pushJob2Task($cachefile);
                    }
                },
                'rejected' => function ($reason, $index) use (&$requests, &$progressBar) {
                    Log::error($requests[$index]->getUri() . ' => failed');
                    $progressBar->advance();
                },
            ]);

            $pool->promise()->wait();
        }

        return $jsonfiles;
    }

    function downloadZipballs($config, $jsonfiles)
    {
        $fileUtils = FileUtils::getInstance();
        $app = App::getInstance();

        $progressBar = new ProgressBarManager(0, count($jsonfiles));
        $progressBar->setFormat("   - " . __FUNCTION__ . ": %current%/%max% [%bar%] %percent%%");

        foreach ($jsonfiles as $file) {
            $progressBar->advance();

            $originContent = file_get_contents($file);
            $packageData = json_decode($originContent, true);

            foreach ($packageData['packages'] as $packageName => $versions) {
                foreach ($versions as $verNumber => $vMeta) {
                    $app->terminated and exit();

                    // 废弃的包 dist url 为null，跳过不处理
                    // bananeapocalypse/nuitinfo2013api
                    // This package is abandoned and no longer maintained. No replacement package was suggested.

                    if (!$vMeta['dist']['url']) {
                        Log::error('发现异常包，跳过: ' . $vMeta['dist']['url']);
                        continue;
                    }

                    // 保存 github/bitbucket ... 真实对应下载地址
                    $zipFile = $config->distdir . $packageName . '/' . $vMeta['dist']['reference'] . '.zip';
                    if (!file_exists($zipFile)) {
                        $fileUtils->storeFile($zipFile, $vMeta['dist']['url']);
                        if (!$app->getConfig()->cloudsync) continue;
                        $app->pushJob2Task($zipFile);
//                    $app->getConfig()->isPrefetch ? $app->getCloud()->prefetchDistFile($zipFile) : $app->getCloud()->pushOneFile($zipFile);
                    }
                }
            }
        }
    }


    /**
     * 更新 packages.json
     * @param $config
     */
    function flushFiles($config)
    {
        $app = App::getInstance();
        $cachedir = $config->cachedir;
        $packages = json_decode(file_get_contents($cachedir.'packages.json.new'));
        $packages->mirrors = [
            [
                'dist-url' => $config->distUrl . '%package%/%reference%.%type%',
                'preferred' => true,
            ]
        ];

        $packages->update_at = date('Y-m-d H:i:s', $app->timestamp);
        file_put_contents($config->cachedir . 'packages.json', json_encode($packages));
        unlink($config->cachedir . 'packages.json.new');

        $app->getConfig()->cloudsync and $app->pushJob2Task($config->cachedir . 'packages.json');
        Log::debug('finished! flushFiles...');
    }

    function request($url)
    {
        try {
            $client = new Client([RequestOptions::TIMEOUT => App::getInstance()->getConfig()->timeout]);
            $res = $client->get($url);
            return (string)$res->getBody();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return '';
        }
    }
}
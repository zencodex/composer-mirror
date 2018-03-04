<?php

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use ProgressBar\Manager as ProgressBarManager;
use zencodex\PackagistCrawler\Cloud;
use zencodex\PackagistCrawler\ExpiredFileManager;
use zencodex\PackagistCrawler\Log;
use function Amp\asyncCall;

require_once __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/config.php')) {
    $config = require __DIR__ . '/config.php';
} else {
    $config = require __DIR__ . '/config.default.php';
}

declare(ticks = 1);
@exec('ulimit -n 10000');
ini_set('memory_limit', '1G');
set_time_limit($config->timeout);
putenv("GUZZLE_CURL_SELECT_TIMEOUT=" . $config->timeout);

//if (file_exists($config->lockfile)) {
//    throw new \RuntimeException("$config->lockfile exists");
//}

$config->cloudsync or Log::warn('NOTE: WOULD NOT SYNC TO CLOUD');

// 检测并生成必要目录
file_exists($config->cachedir) or mkdir($config->cachedir, 0777, true);
file_exists($config->distdir) or mkdir($config->distdir, 0777, true);

//touch($config->lockfile);
//register_shutdown_function(function() use($config) {
//    unlink($config->lockfile);
//});

/////////////////////////////////////////////////////////////////////////////////////////

$globals = new \stdClass;
$globals->q = new \SplQueue;
$globals->expiredManager = new ExpiredFileManager($config->expiredDb, $config->expireMinutes);
$globals->terminated = 0;
$globals->cloud = new Cloud($config);

set_exception_handler(function (Throwable $ex) use ($globals) {
    unset($globals->expiredManager);
    Log::error('=============== set_exception_handler =================');
    Log::error($ex->getTraceAsString());
});

$signal_handler = function ($signal) use(&$globals) {
    Log::warn("kill signal, please wait ...");
    $globals->terminated = 1;
};

pcntl_signal(SIGINT, $signal_handler);  // Ctrl + C
pcntl_signal(SIGCHLD, $signal_handler);
pcntl_signal(SIGTSTP, $signal_handler);  // Ctrl + Z

do {
//    $globals->terminated and exit();
//    $globals->retry = false;

    // STEP 1
    $providers = downloadProviders($config);
//    if ($globals->retry) continue;

    // STEP 2
    $jsonfiles = downloadPackages($config, $providers);
//    if ($globals->retry) continue;

    // STEP 3
    downloadZipballs($config, $jsonfiles);
//    if ($globals->retry) continue;

//} while ($globals->retry);
} while (0);

flushFiles($config);

Log::warn("wait for 120s....");
sleep(120);
exit;

/**
 * packages.json & provider-xxx$xxx.json downloader
 */
function downloadProviders($config)
{
    global $globals;
    $cachedir = $config->cachedir;
    $packagesCache = $cachedir . 'packages.json';

    $packages = json_decode(request($config->packagistUrl . '/packages.json'));
    foreach (explode(' ', 'notify notify-batch search') as $k) {
        if (0 === strpos($packages->$k, '/')) {
            $packages->$k = 'https://packagist.org' . $packages->$k;
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

    foreach ($packages->{'provider-includes'} as $tpl => $version) {
        $globals->terminated and exit();

        $fileurl = str_replace('%hash%', $version->sha256, $tpl);
        $cachename = $cachedir . $fileurl;
        $providers[] = $cachename;

        if (!file_exists($cachename)) {
            $data = request($config->packagistUrl . '/' . $fileurl);
            if ($data) {
                $oldcache = $cachedir . str_replace('%hash%.json', '*', $tpl);
                if ($glob = glob($oldcache)) {
                    foreach ($glob as $old) {
                        $globals->expiredManager->add($old, time());
                    }
                }
                storeFile($cachename, $data);

                if ($config->cloudsync) {
                    $globals->cloud->pushOneFile($cachename);
                }
            } else {
                $globals->retry = true;
            }
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
    global $globals;
    $cachedir = $config->cachedir;
    $i = 1;
    $numberOfProviders = count($providers);
    $jsonfiles = [];
    $packageObjs = [];

    foreach ($providers as $providerjson) {
        $list = json_decode(file_get_contents($providerjson));
        if (!$list || empty($list->providers)) continue;

        $list = $list->providers;
        $all = count((array)$list);

        $progressBar = new ProgressBarManager(0, $all);
        echo "   - Provider {$i}/{$numberOfProviders}:\n";
        $progressBar->setFormat("      - Package: %current%/%max% [%bar%] %percent%%");

        $sum = 0;
        foreach ($list as $packageName => $provider) {
            $globals->terminated and exit();

            $progressBar->advance();
            ++$sum;
            $url = "$config->packagistUrl/p/$packageName\$$provider->sha256.json";
            $cachefile = $cachedir . str_replace("$config->packagistUrl/", '', $url);

            if (file_exists($cachefile)) continue;
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
            $req = new Request('GET', $package->url);
            $req->sha256 = $package->sha256;
            $req->packageName = $package->packageName;
            $requests[] = $req;
        }

        $pool = new Pool($client, $requests, [
            'concurrency' => $config->maxConnections,
            'fulfilled' => function ($res, $index) use (&$jsonfiles, &$requests, &$config, &$globals, $progressBar) {

                $req = $requests[$index];
                $cachedir = $config->cachedir;
                $progressBar->advance();

                if (200 !== $res->getStatusCode() || $req->sha256 !== hash('sha256', (string)$res->getBody())) {
                    Log::error( "\t sha256 wrong => ". $req->getUri());
                    $globals->retry = true;
                    return;
                }

                $cachefile = $cachedir
                    . str_replace("$config->packagistUrl/", '', $req->getUri());
                //                $cachefile2 = $cachedir . '/p/' . $req->packageName . '.json';
                //                $urls[] = $config->url . '/p/' . $req->packageName . '.json';
                $jsonfiles[] = $cachefile;

                if ($glob = glob("{$cachedir}p/$req->packageName\$*")) {
                    foreach ($glob as $old) {
                        $globals->expiredManager->add($old, time());
                    }
                }
                storeFile($cachefile, (string)$res->getBody());
                //                storeFile($cachefile2, (string)$res->getBody());

                if ($config->cloudsync) {
                    $globals->cloud->pushOneFile($cachefile);
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
    $progressBar = new ProgressBarManager(0, count($jsonfiles));
    $progressBar->setFormat("   - " . __FUNCTION__ . ": %current%/%max% [%bar%] %percent%%");

    foreach ($jsonfiles as $file) {
        $progressBar->advance();

        $originContent = file_get_contents($file);
        $packageData = json_decode($originContent, true);

        foreach ($packageData['packages'] as $packageName => $versions) {
            foreach ($versions as $verNumber => $vMeta) {
                asyncCall(function () use ($verNumber, $vMeta, $packageName) {
                    global $globals, $config;

                    // 废弃的包 dist url 为null，跳过不处理
                    // bananeapocalypse/nuitinfo2013api
                    // This package is abandoned and no longer maintained. No replacement package was suggested.

                    if (!$vMeta['dist']['url']) {
                        Log::error('发现异常包，跳过: ' . $vMeta['dist']['url']);
                    }

                    // 保存 github/bitbucket ... 真实对应下载地址
                    $zipFile = $config->distdir . $packageName . '/' . $vMeta['dist']['reference'] . '.zip';
                    if (!file_exists($zipFile)) {
                        storeFile($zipFile, $vMeta['dist']['url']);
                        $config->cloudsync and $globals->cloud->prefetchDistFile($zipFile);
                    }
                });
            }
        }
    }
}

function flushFiles($config)
{
    global $globals;
    if ($globals->cloud->badCountOfProviderPackages() == 0) {
        $cachedir = $config->cachedir;
        $packages = json_decode(file_get_contents($cachedir.'packages.json.new'));
        $packages->mirrors = [
            [
                'dist-url' => $config->distUrl . '%package%/%reference%.%type%',
                'preferred' => true,
            ]
        ];
        $packages->update_at = date('Y-m-d H:i:s');
        file_put_contents($config->cachedir . 'packages.json', json_encode($packages));

        if ($config->cloudsync) {
            $globals->cloud->pushOneFile($config->cachedir . 'packages.json');
            $globals->cloud->refreshRemoteFile($config->mirrorUrl . '/packages.json');
            generateHtml($config, $packages->update_at);
        }
        unlink($config->cachedir . 'packages.json.new');
        Log::debug('finished! flushing...');
    } else {
        Log::error('!!! update error !!!');
    }
}

function storeFile($file, $data)
{
    if (!file_exists(dirname($file))) {
        mkdir(dirname($file), 0777, true);
    }
    file_put_contents($file, $data, LOCK_EX);
}

function request($url)
{
    global $config;
    try {
        $client = new Client([ RequestOptions::TIMEOUT => $config->timeout]);
        $res = $client->get($url);
        return (string)$res->getBody();
    } catch (\Exception $e) {
        Log::error($e->getMessage());
        return '';
    }
}

function generateHtml($_config, $time)
{
    ob_start();
    $update_at = $time;
    include __DIR__ . '/index.html.php';
    file_put_contents($_config->cachedir . '/index.html', ob_get_clean());
}

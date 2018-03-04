<?php
/**
 * Author: ZenCodex <v@yinqisen.cn>
 * Date: 2/28/18
 * Time: 1:05 PM
 */

namespace zencodex\PackagistCrawler;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Upyun\Config;
use Upyun\Upyun;

class Cloud
{
    private $config;
    private $client;
    private $lastUpload = [];

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new Upyun( $this->bucketConfig() );
    }

    // 将文件上传到 又拍云
    public function pushOneFile($file)
    {
        if (!file_exists($file)) {
            unset($this->globals->expiredManager);
            throw new \RuntimeException('pushOneFile, Not found => ' . $file);
        }

        if (count($this->lastUpload) > 1000) {
            $this->lastUpload = [];
        }

        // fix issue: {"msg":"too many requests of the same uri","code":42900002 }
        // sleep to wait
        if (isset($this->lastUpload[$file]) && (time() - intval($this->lastUpload[$file]) < 10)) {
            Log::warn('wait 10s, workaround => too many requests of the same uri');
            sleep(10);
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext == 'zip') {
            $start = strlen($this->config->distdir);
            $uri = substr($file, $start);
            $postUrl = file_get_contents($file);

            $tmpfile = tempnam(null, null);
            try {
                $downloader = new Client([ RequestOptions::TIMEOUT => $this->config->timeout ]);
                $downloader->get($postUrl, [ 'sink' => $tmpfile ]);
            } catch (\Exception $e) {
                Log::error('pushOneFile '. $file .' => github/xxxx error!!!');
                Log::error($e->getMessage());
                unlink($tmpfile);
                return -2;
            }
        } else if ($ext == 'json') {
            $start = strlen($this->config->cachedir);
            $uri = substr($file, $start);
            $tmpfile = $file;
        } else {
            return -3;
        }

        try {
            $f = fopen($tmpfile, 'rb');
            // 上传到又拍云
            $this->client->setConfig($this->bucketConfig($ext));
            $this->client->write($uri, $f);
            Log::info('pushOneFile success => '. $file);
            return 1;
        } catch (\Exception $e) {
            Log::error("pushOneFile => $file \n" . $e->getMessage());
            return -1;
        }
    }

    public function prefetchDistFile($zipFile)
    {
        $start = strlen($this->config->distdir);
        $uri = substr($zipFile, $start);
        $postUrl = file_get_contents($zipFile);

        $tasks = [[
            'url' => $postUrl,
            'overwrite' => true,
            'save_as' => $uri,
        ]];

        try {
            $bucketConfig = $this->bucketConfig('zip');
            $bucketConfig->processNotifyUrl = 'http://127.0.0.1';
            $this->client->setConfig($bucketConfig);
            $result = $this->client->process($tasks, Upyun::$PROCESS_TYPE_SYNC_FILE);
            Log::info('prefetchDistFile => ' . $zipFile);
        } catch (\Exception $e) {
            Log::error('prefetchDistFile => '. $e->getMessage());
        }
    }

    private function bucketConfig($ext = 'zip')
    {
        $bucketConfig = new Config(
            $this->config->upyun->bucket->$ext,
            $this->config->upyun->operator,
            $this->config->upyun->password
        );

        $bucketConfig->timeout = $this->config->timeout;
        $bucketConfig->sizeBoundary = 121457280; // 70M
        return $bucketConfig;
    }

    public function removeRemoteFile($file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext == 'zip') {
            $start = strlen($this->config->distdir);
            $uri = substr($file, $start);
        } else if ($ext == 'json') {
            $start = strlen($this->config->cachedir);
            $uri = substr($file, $start);
        } else {
            return false;
        }

        try {
            $this->client->setConfig( $this->bucketConfig($ext) );
            $this->client->delete($uri, true);
            Log::info('removeRemoteFile => ' . $file);
            return true;
        } catch (\Exception $e) {
            Log::error('removeRemoteFile => '. $e->getMessage());
            return false;
        }
    }

    public function refreshRemoteFile($remoteUrl)
    {
//        $ext = pathinfo($remoteUrl, PATHINFO_EXTENSION);
//        $client = new Upyun( $this->bucketConfig() );
        try {
            $result = $this->client->purge($remoteUrl);
            Log::debug("refreshCdnCache => $remoteUrl \n". json_encode($result));
        } catch (\Exception $e) {
            Log::error('refreshCdnCache => '. $e->getMessage());
        }
    }

    /**
     * check sha256
     */
    function badCountOfAllPackages()
    {
        Log::info('------------- ' . __FUNCTION__ . ' -------------');

        $cachedir = $this->config->cachedir;
        $packagejson = json_decode(file_get_contents($cachedir.'packages.json'));

        $i = $j = 0;
        foreach ($packagejson->{'provider-includes'} as $tpl => $provider) {
            $providerjson = str_replace('%hash%', $provider->sha256, $tpl);
            $packages = json_decode(file_get_contents($cachedir.$providerjson));

            foreach ($packages->providers as $tpl2 => $sha) {
                $file = $cachedir . "p/$tpl2\$$sha->sha256.json";
                if (!file_exists($file)) {
                    Log::error('LOST FILE => ' . $file);
                    ++$i;
                } elseif ($sha->sha256 !== hash_file('sha256', $file)) {
                    Log::error('HASH ERROR => ' . $file);
                    ++$i;
                    unlink($file);
                } else {
                    ++$j;
                }
            }
        }

        Log::info($i . ' / ' . ($i + $j));
        return $i;
    }

    function badCountOfProviderPackages()
    {
        Log::info('------------- ' . __FUNCTION__ . ' -------------');
        $cachedir = $this->config->cachedir;
        $packagejson = json_decode(file_get_contents($cachedir.'packages.json.new'));

        $i = $j = 0;
        foreach ($packagejson->{'provider-includes'} as $tpl => $provider) {
            $providerjson = str_replace('%hash%', $provider->sha256, $tpl);
            $file = $cachedir.$providerjson;
            if (!file_exists($file)) {
                Log::error('LOST FILE => ' . $file);
                ++$i;
            } elseif ($provider->sha256 !== hash_file('sha256', $file)) {
                Log::error('HASH ERROR => ' . $file);
                ++$i;
                unlink($file);
            } else {
                ++$j;
            }
        }

        Log::info($i . ' / ' . ($i + $j));
        return $i;
    }
}
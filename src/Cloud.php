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

    private function bucketConfig($ext = 'zip')
    {
        $bucketConfig = new Config(
            $this->config->upyun->bucket->$ext,
            $this->config->upyun->operator,
            $this->config->upyun->password
        );

        $bucketConfig->timeout = $this->config->timeout;
        $bucketConfig->sizeBoundary = 121457280;
        return $bucketConfig;
    }

    private function pickFileInfo($file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext == 'zip') {
            $start = strlen($this->config->distdir);
            $uri = substr($file, $start);
            $postUrl = file_get_contents($file);

            $tmpfile = tempnam(null, 'composer_');
            try {
                $downloader = new Client([ RequestOptions::TIMEOUT => $this->config->timeout ]);
                $downloader->get($postUrl, ['sink' => $tmpfile]);
            } catch (\Exception $e) {
                Log::error('pushOneFile '. $file .' => github/xxxx error!!!');
                Log::error($e->getMessage());
                $tmpfile = '';
            }
        } else if ($ext == 'json') {
            $start = strlen($this->config->cachedir);
            $uri = substr($file, $start);
            $tmpfile = $file;
        } else {
            throw new \RuntimeException("不支持的文件扩展 $ext");
        }

        return [$ext, $uri, $tmpfile];
    }

    // 将文件上传到 又拍云
    public function pushOneFile($file)
    {
        Log::info($file);
        if (!file_exists($file)) {
            unset($this->globals->expiredManager);
            throw new \RuntimeException('pushOneFile, Not found => ' . $file);
        }

        // default value for return
        $ret = -1;
        if (count($this->lastUpload) > 1000) {
            $this->lastUpload = [];
        }

        // fix issue: {"msg":"too many requests of the same uri","code":42900002 }
        // sleep to wait
        if (isset($this->lastUpload[$file]) && (time() - intval($this->lastUpload[$file]) < 20)) {
            Log::warn('wait 10s, workaround => too many requests of the same uri');
            sleep(20);
        }

        [$ext, $uri, $tmpfile] = $this->pickFileInfo($file);
        if (empty($tmpfile)) goto __END__;

        try {
            // 上传到又拍云
            $this->client->setConfig($this->bucketConfig($ext));

            $f = fopen($tmpfile, 'rb');
            $this->client->write($uri, $f);
//            $this->client->write($uri, file_get_contents($tmpfile));
            Log::info('pushOneFile success => '. $file);
            $ret = 1;
        } catch (\Exception $e) {
            Log::error("pushOneFile => $file \n" . $e->getMessage());
        }

    __END__:
        $ext == 'zip' and $tmpfile and unlink($tmpfile);
        return $ret;
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
            Log::debug("refreshCdnCache => $remoteUrl \n");
        } catch (\Exception $e) {
            Log::error('refreshCdnCache => '. $e->getMessage());
        }
    }
}
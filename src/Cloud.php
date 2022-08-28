<?php

/*
|--------------------------------------------------------------------------
| 云端文件的相关功能
|--------------------------------------------------------------------------
*/

namespace ZenCodex\ComposerMirror;

use Upyun\Upyun;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use ZenCodex\ComposerMirror\Support\ClientHandlerPlugin;

class Cloud
{
    private $config;
    private $lastUpload = [];
    private $_cloudDisks = ['json' => null, 'zip' => null];

    const JSON_CACHE_FILE = 'cached.json';
    const DIST_CACHE_FILE = 'cached.dist';

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 根据 $ext (json/zip) 创建对应 bucket 的对象
     *
     * @param string $ext
     * @return mixed
     */
    public function cloudDisk($ext = 'json')
    {
        if (!$this->_cloudDisks[$ext]) {
            $cloudConfig = $this->config->cloudDisk->config;
            $cloudConfig['bucket'] = $this->config->cloudDisk->bucketMap[$ext];

            $adapter = new $this->config->cloudDisk->adapter($cloudConfig);
            $cloudDisk = new Filesystem($adapter, new Config([ 'disable_asserts' => true]));
            $cloudDisk->addPlugin(new ClientHandlerPlugin());

            $this->_cloudDisks[$ext] = $cloudDisk;
        }

        return $this->_cloudDisks[$ext];
    }

    private function pickFileInfo($file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext == 'zip') {
            $start = strlen($this->config->distdir);
            $uri = substr($file, $start);
            $postUrl = file_get_contents($file);

            $tmpfile = tempnam('', 'composer_');
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
            throw new \RuntimeException('pushOneFile, Not found => ' . $file);
        }

        // default value for return
        $ret = -1;
        if (count($this->lastUpload) > 1000) {
            $this->lastUpload = [];
        }

        // fix issue: {"msg":"too many requests of the same uri","code":42900002 }
        // sleep to wait
        if (isset($this->lastUpload[$file]) && (time() - intval($this->lastUpload[$file]) < 30)) {
            Log::warn('wait 10s, workaround => too many requests of the same uri');
            sleep(30);
        }

        [$ext, $uri, $tmpfile] = $this->pickFileInfo($file);
        if (empty($tmpfile)) goto __END__;

        try {
            $f = fopen($tmpfile, 'rb');
            // 根据扩展名指定bucket，上传到又拍云
            $this->cloudDisk($ext)->writeStream($uri, $f);
            Log::debug('pushOneFile success => '. $file);
            $ret = 1;
        } catch (\Exception $e) {
            Log::error("pushOneFile => $file \n" . $e->getMessage());
        }

    __END__:
        $ext == 'zip' and file_exists($tmpfile) and unlink($tmpfile);
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
            $cloudClient = $this->cloudDisk('zip')->getClientHandler(['processNotifyUrl' => 'http://127.0.0.1']);
            $result = $cloudClient->process($tasks, Upyun::$PROCESS_TYPE_SYNC_FILE);
            Log::info('prefetchDistFile => ' . $zipFile);
        } catch (\Exception $e) {
            Log::error('prefetchDistFile => '. $e->getMessage());
        }
    }

    public function removeRemoteFile($file)
    {
        if (strpos($file, 'composer.phar') > 0) return;

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
            $this->cloudDisk($ext)->delete($uri);
            Log::info('removeRemoteFile => ' . $file);
            return true;
        } catch (\Exception $e) {
            Log::error('removeRemoteFile => '. $e->getMessage());
            return false;
        }
    }

    public function refreshRemoteFile($remoteUrl)
    {
        try {
            $cloudClient = $this->cloudDisk()->getClientHandler();
            $result = $cloudClient->purge($remoteUrl);
            Log::debug("refreshCdnCache => $remoteUrl \n");
        } catch (\Exception $e) {
            Log::error('refreshCdnCache => '. $e->getMessage());
        }
    }
}

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
    private $_cachedCloudFiles;

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

    public function clearCloudJsonFiles()
    {
        $this->loadCachedCloudFiles($this->config->cachedir);
        $this->client->setConfig( $this->bucketConfig('json') );
        $this->travels(rtrim($this->config->cachedir, '/'), '/');
        unlink($this->config->distdir . 'cloudfiles.txt');
    }

    public function clearCloudDistFiles()
    {
        $this->loadCachedCloudFiles($this->config->distdir);
        $this->client->setConfig( $this->bucketConfig('zip') );
        $this->travels(rtrim($this->config->distdir, '/'), '/');
        unlink($this->config->distdir . 'cloudfiles.txt');
    }

    public function travels($local = '', $dir = '/')
    {
        $params = ['X-List-Limit' => 5000];

        if ($dir[ strlen($dir) - 1 ] !== '/') {
            $dir .= '/';
        }

        $isEmptyFolder = true;
        do {
            try {
                $res = $this->client->read($dir, null, $params);
                $isEmptyFolder = $this->handleCloudFiles($local, $dir, $res['files']);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                break;
            }

            if (isset($res['iter'])) {
                $params['X-List-Iter'] = $res['iter'];
            }

        } while (!$res['is_end']);

        // 删除空文件夹
        if ($isEmptyFolder) {
            $localdir = $local . $dir;

            if (is_dir($localdir)) {
                Log::warn("remote is empty, but local is not => $localdir");
                @exec("rm -rf $localdir");
            } else {
                Log::warn("remove remote empty folder => $dir");
            }

            try {
                $this->client->deleteDir($dir);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }

    public function handleCloudFiles($local, $dir, $files)
    {
        foreach ($files as $fileObj) {
            $isEmptyFolder = false;

            $uri = $dir . $fileObj['name'];
            if (isset($this->_cachedCloudFiles[$uri])) {
                Log::warn("skip uri => " . $local . $uri);
                continue;
            }

            Log::info("name = {$fileObj['name']}, type = {$fileObj['type']}, uri = " . $local . $uri);
            // 如果为目录，递归查找
            if ($fileObj['type'] == 'F') {
                $this->travels($local, $uri);
            } else {
                // composer.phar
                if (strpos($uri, 'composer.phar') > 0) {
                    Log::warn("skip composer.phar");
                    continue;
                }

                // 判断本地文件, 不存在，删除远程
                if (!file_exists($local . $uri)) {
                    Log::warn("local not found, so remove remote file => $uri");
                    try {
                        $this->client->delete($uri, true);
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());
                    }
                }
            }

            if ($dir === '/') {
                file_put_contents($local . '/cloudfiles.txt', $uri . PHP_EOL, FILE_APPEND);
            }
        }

        return $isEmptyFolder;
    }

    public function loadCachedCloudFiles($localdir)
    {
        if (file_exists($localdir . 'cloudfiles.txt')) {
            $cloudfiles = file($localdir . 'cloudfiles.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($cloudfiles as $f) {
                $this->_cachedCloudFiles[$f] = true;
            }
        }
    }
}
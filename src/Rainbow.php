<?php
/**
 * Author: ZenCodex <v@yinqisen.cn>
 * Date: 2/28/18
 * Time: 1:05 PM
 */

namespace zencodex\PackagistCrawler;
use Upyun\Config;
use Upyun\Upyun;

class Rainbow
{
    private $config;
    private $client;

    private $_uriMapTable;
    private $_dirCacheTable;

    private $_uriMapFile;
    private $_dirCacheFile;

    public const JSON_URI_MAP = 'jsonCloud.map';
    public const DIST_URI_MAP = 'distCloud.map';

    const JSON_DIR_CACHE = '.json.dir';
    const DIST_DIR_CACHE = '.dist.dir';

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

    public function mapCloudJsonFiles()
    {
        $this->_uriMapFile = $this->config->dbdir . self::JSON_URI_MAP;
        $this->_dirCacheFile = $this->config->dbdir . self::JSON_DIR_CACHE;

        $this->loadCachedCloudFiles();
        $this->client->setConfig( $this->bucketConfig('json') );

        $this->travels(rtrim($this->config->cachedir, '/'), '/');
        unlink($this->_dirCacheFile);
    }

    public function mapCloudDistFiles()
    {
        $this->_uriMapFile = $this->config->dbdir . self::DIST_URI_MAP;
        $this->_dirCacheFile = $this->config->dbdir . self::DIST_DIR_CACHE;

        $this->loadCachedCloudFiles();
        $this->client->setConfig( $this->bucketConfig('zip') );

        $this->travels(rtrim($this->config->distdir, '/'), '/');
        unlink($this->_dirCacheFile);
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
        $isEmptyFolder = true;

        foreach ($files as $fileObj) {
            $isEmptyFolder = false;

            $uri = $dir . $fileObj['name'];
            if (isset($this->_dirCacheTable[$uri])) {
                Log::warn("skip uri => " . $local . $uri);
                continue;
            }

            Log::info("name = {$fileObj['name']}, type = {$fileObj['type']}, uri = " . $local . $uri);
            // 如果为目录，递归查找
            if ($fileObj['type'] == 'F') {
                $this->travels($local, $uri);
            } else {
                file_put_contents($this->_uriMapFile, $uri . PHP_EOL, FILE_APPEND);
//                // composer.phar
//                if (strpos($uri, 'composer.phar') > 0) {
//                    Log::warn("skip composer.phar");
//                    continue;
//                }
            }

            if ($dir === '/') {
                $this->appendCloudUri($uri);
            }
        }

        return $isEmptyFolder;
    }

    private function appendCloudUri($uri)
    {
        file_put_contents($this->_dirCacheFile, $uri . PHP_EOL, FILE_APPEND);
    }

    public function loadCachedCloudFiles()
    {
        if (file_exists($this->_uriMapFile)) {
            $lines = file($this->_uriMapFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $this->_uriMapTable[$line] = true;
            }
        }

        if (file_exists($this->_dirCacheFile)) {
            $lines = file($this->_dirCacheFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $this->_dirCacheTable[$line] = true;
            }
        }
    }
}
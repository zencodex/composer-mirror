<?php

/*
|--------------------------------------------------------------------------
| 本地文件的相关功能: Hash检测，json/zip 扫描 ...
|--------------------------------------------------------------------------
*/

namespace zencodex\ComposerMirror;

use ProgressBar\Manager as ProgressBarManager;

class FileUtils extends InstanceBase
{
    private static $_instance;
    private $config;
    private $cloud;

    public static function getInstance()
    {
        $instance = self::$_instance;
        if ($instance == null) {
            $instance = new static;
            $instance->cloud = App::getCloud();
            $instance->config = App::getConfig();
            self::$_instance = $instance;
        }
        return $instance;
    }

    /**
     * 检测文件的hash值
     * @param $file
     */
    protected function checkHashOfFile($file)
    {
        // validate file hash
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext == 'json' && ($startpos = strpos($file, '$')) !== false) {
            $aHash = substr($file, $startpos + 1, 64);
            $bHash = hash('sha256', file_get_contents($file));
            if ($aHash !== $bHash) {
                unlink($file);

                // remove remote json file
                if ($this->config->cloudsync) {
                    $this->cloud->removeRemoteFile($file);
                }

                throw new \RuntimeException("签名错误!!! $aHash : $bHash, $file");
            }
        }
    }

    /**
     * 保存文件
     * @param $file
     * @param $data
     */
    protected function storeFile($file, $data)
    {
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        file_put_contents($file, $data, LOCK_EX);
        $this->checkHashOfFile($file);
    }

    /**
     * 更新文件的修改和访问时间
     * @param $file
     */
    protected function touchFile($file, $timestamp)
    {
//    checkHashOfFile($file);
        touch($file, $timestamp, $timestamp);
    }

    /**
     * check sha256
     */
    protected function badCountOfAllPackages()
    {
        Log::info('------------- ' . __FUNCTION__ . ' -------------');
        $app = App::getInstance();

        $cachedir = $this->config->cachedir;
        $packagejson = json_decode(file_get_contents($cachedir.'packages.json'));

        $i = $j = 0;
        $errCount = $touchCount = $allCount = 0;

        foreach ($packagejson->{'provider-includes'} as $tpl => $provider) {
            $providerjson = str_replace('%hash%', $provider->sha256, $tpl);
            $packages = json_decode(file_get_contents($cachedir.$providerjson));

            $progressBar = new ProgressBarManager(0, count((array)$packages->providers));
            $progressBar->setFormat("$tpl : %current%/%max% [%bar%] %percent%%");

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

                // 检测zip包
                $originContent = file_get_contents($file);
                $packageData = json_decode($originContent, true);
                foreach ($packageData['packages'] as $packageName => $versions) {
                    foreach ($versions as $verNumber => $vMeta) {

                        // 废弃的包 dist url 为null，跳过不处理
                        // bananeapocalypse/nuitinfo2013api
                        // This package is abandoned and no longer maintained. No replacement package was suggested.

                        if (!$vMeta['dist']['url']) {
//                            Log::error('发现异常包，跳过: ' . $vMeta['dist']['url']);
                            ++$errCount;
                            continue;
                        }

                        // 保存 github/bitbucket ... 真实对应下载地址
                        $zipFile = $this->config->distdir . $packageName . '/' . $vMeta['dist']['reference'] . '.zip';
                        if (!file_exists($zipFile)) {
                            $this->storeFile($zipFile, $vMeta['dist']['url']);
                            if (!$this->config->cloudsync) continue;
                            $app->pushJob2Task($zipFile);
                        } else {
                            $this->touchFile($zipFile, $app->timestamp);
                            ++$touchCount;
                        }

                        ++$allCount;
                    }
                }

                $progressBar->advance();
            }
        }

        // 保存时间
        $line = implode(',', [$app->timestamp, $errCount, $touchCount, $allCount]);
        file_put_contents($this->config->dbdir . 'touchall.log', $line . PHP_EOL, FILE_APPEND);
        Log::info($i . ' / ' . ($i + $j));
        return $i;
    }

    protected function badCountOfProviderPackages($baseFile = 'packages.json.new')
    {
        Log::info('------------- ' . __FUNCTION__ . ' -------------');
        $cachedir = $this->config->cachedir;
        $packagejson = json_decode(file_get_contents($cachedir . $baseFile));

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

                // 删除错误文件，重新下载
                unlink($file);
            } else {
                ++$j;
            }
        }

        Log::info($i . ' / ' . ($i + $j));
        return $i;
    }
}
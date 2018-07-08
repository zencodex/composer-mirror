<?php
/**
 * Author: ZenCodex <v@yinqisen.cn>
 * Date: 2/28/18
 * Time: 1:05 PM
 */

namespace zencodex\PackagistCrawler;
use ProgressBar\Manager as ProgressBarManager;

class FileUtils
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
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

                $progressBar->advance();
            }
        }

        Log::info($i . ' / ' . ($i + $j));
        return $i;
    }

    function badCountOfProviderPackages($baseFile = 'packages.json.new')
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
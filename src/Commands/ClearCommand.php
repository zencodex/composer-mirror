<?php

namespace zencodex\PackagistCrawler\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use zencodex\PackagistCrawler\App;
use zencodex\PackagistCrawler\Cloud;
use zencodex\PackagistCrawler\Log;

class ClearCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName("app:clear")
            ->setDescription('清理过期文件或又拍云反向清理');

        $this
            ->addOption('--expired', null, null, '清理过期文件')
            ->addOption('--cloud', null, null, '又拍云反向清理，时间慢，大约2天');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = App::getConfig();

        if ($input->getOption('expired')) {
            $this->clearOutdatedFiles();
        }

        if ($input->getOption('cloud')) {
            $cloud = new Cloud($config);
            $cloud->clearCloudDistFiles();
            $cloud->clearCloudJsonFiles();
        }
    }

    function clearOutdatedFiles()
    {
        $config = App::getConfig();
        $cloud = new Cloud($config);

        $packages = json_decode(file_get_contents($config->cachedir . 'packages.json'));
        $basetime = strtotime($packages->update_at);

        $finder = new Finder();
        $finder->files()->in($config->cachedir . 'p');

        foreach ($finder as $fileObj) {
            $file = $fileObj->getRealPath();
            // skip "p/provider-xxx%hash%.json
//        if (strpos($file, '/p/provider-')) continue;

            if ($basetime - filemtime($file) > $config->expireMinutes * 60) {
                unlink($file);
                // remove remote json file
                if ($config->cloudsync) {
                    $cloud->removeRemoteFile($file);
                }
                Log::warn("removed file => $file");
            }
        }
    }
}

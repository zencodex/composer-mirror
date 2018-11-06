<?php

namespace zencodex\PackagistCrawler\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use zencodex\PackagistCrawler\FileUtils;

class CheckHashCommand extends Command
{

    protected function configure()
    {
        $this
            ->setName('app:checkhash')
            ->setDescription('检查文件 hash 并修复');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        FileUtils::badCountOfAllPackages();
//        FileUtils::badCountOfProviderPackages('packages.json');
    }
}

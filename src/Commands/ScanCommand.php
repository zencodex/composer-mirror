<?php

namespace ZenCodex\ComposerMirror\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZenCodex\ComposerMirror\App;
use ZenCodex\ComposerMirror\Log;
use ZenCodex\ComposerMirror\FileUtils;

class ScanCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('app:scan')
            ->setDescription('扫描所有包和zip文件，校验 hash ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!App::getInstance()->getClientHandler()) {
            Log::warn('未启用又拍云配置');
            return;
        }

        FileUtils::badCountOfAllPackages();
        // FileUtils::badCountOfProviderPackages('packages.json');
    }
}

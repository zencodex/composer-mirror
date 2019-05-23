<?php

namespace ZenCodex\ComposerMirror\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZenCodex\ComposerMirror\App;
use ZenCodex\ComposerMirror\Rainbow;

class RainbowCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName("app:rainbow")
            ->setDescription('遍历云储存的文件，并缓存在本地，用于对比差异');

//        $this
//            ->addOption('--expired', null, InputOption::VALUE_NONE, '清理过期文件')
//            ->addOption('--cloud', null, InputOption::VALUE_OPTIONAL, '又拍云反向清理，时间慢，大约2天');
        $this
            ->addOption('--step', null, InputOption::VALUE_REQUIRED, '', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = App::getConfig();
        $rainbow = new Rainbow($config);
        $rainbow->mapCloudDistFiles();
    }
}


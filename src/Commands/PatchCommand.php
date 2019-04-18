<?php

namespace zencodex\PackagistCrawler\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use zencodex\PackagistCrawler\App;
use zencodex\PackagistCrawler\Log;

class PatchCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('app:patch')
            ->setDescription('针对最大目录数的限制，采用多目录软连接的方法');

        $this->addOption('--run', null, null, '执行目录分离，不带参数仅仅是查看');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = App::getConfig();

        if ($input->getOption('run')) {
            $pdir = $config->cachedir . 'p';
            $p2dir = $config->cachedir . 'p2';
            $this->split($pdir, $p2dir);

            $pdir = dirname($config->distdir) . '/dist';
            $p2dir = dirname($config->distdir) . '/dist2';
            $this->split($pdir, $p2dir);
        } else {
            // 检测目录数量
            $pdir = $config->cachedir . 'p';
            $count = 0;
            foreach (glob($pdir . '/*/') as $dir) {
                if (is_link( rtrim($dir, '/') )) continue;
                if (is_dir($dir))  $count++;
            }
            echo "json Folders Count = $count \n";

            //////////////////////////////////////////////////////////
            $pdir = dirname($config->distdir) . '/dist';
            $count = 0;
            foreach (glob($pdir . '/*/') as $dir) {
                if (is_link( rtrim($dir, '/') )) continue;
                if (is_dir($dir))  $count++;
            }
            echo "dist Folders Count = $count \n";
        }
    }

    public function split($pdir, $p2dir)
    {
        $to_move = ['0', '1', '2', '3', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 's', 'k', 'y', 'z'];
        // $to_move = ['0', '1'];
        if (!file_exists($p2dir)) {
            mkdir($p2dir, 0777, true);
        }

        $count = 0;
        foreach (glob($pdir . '/*/') as $dir) {
            $dir = substr($dir, strlen($pdir) + 1);
            $linkdir = $pdir . '/' . rtrim($dir, '/');

            // 已经创建link
            if (is_link($linkdir)) {
                Log::warn("SOFT LINK => $linkdir");

                if (linkinfo($linkdir)) {
                    continue;
                } else {
                    // 无效link 删除
                    unlink($linkdir);
                }
            }

            // 目标存在，重新建立 link
            if (file_exists($p2dir . '/' . $dir)) {
                symlink($p2dir . '/' . $dir, $linkdir);
                continue;
            }

            if (in_array($dir[0], $to_move)) {
                rename($pdir . '/' . $dir, $p2dir . '/' . $dir);
                symlink($p2dir . '/' . $dir, $linkdir);
                echo $pdir . '/' . $dir . PHP_EOL;
                $count++;
            }
        }

        echo $count . PHP_EOL;
    }

}

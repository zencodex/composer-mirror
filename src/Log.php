<?php

/**
 * Author: ZenCodex <v@yinqisen.cn>
 * Date: 2/28/18
 * Time: 12:54 PM
 */

namespace zencodex\PackagistCrawler;

class Log {

    public static function error($str)
    {
        Console::log($str, 'red');
    }

    public static function debug($str)
    {
        Console::log($str, 'light_green');
    }

    public static function warn($str)
    {
        Console::log($str, 'yellow');
    }

    public static function info($str)
    {
        Console::log($str);
    }
}
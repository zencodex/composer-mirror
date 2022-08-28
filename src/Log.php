<?php

/*
|--------------------------------------------------------------------------
| Log 日志函数
|--------------------------------------------------------------------------
*/

namespace ZenCodex\ComposerMirror;

class Log
{
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
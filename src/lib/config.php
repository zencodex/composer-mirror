<?php

defined('ROOTDIR') || define('ROOTDIR', __DIR__ . '/../..');

if (file_exists(ROOTDIR . '/config.php')) {
    $config = require ROOTDIR . '/config.php';
} else {
    $config = require ROOTDIR . '/config.default.php';
}

return $config;
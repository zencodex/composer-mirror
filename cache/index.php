<?php

/**
 * Author: ZenCodex <v@yinqisen.cn>
 * Date: 3/3/18
 * Time: 10:06 PM
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../config.php')) {
    $config = require __DIR__ . '/../config.php';
} else {
    $config = require __DIR__ . '/../config.default.php';
}

$input = $_SERVER['QUERY_STRING'] ?: $_SERVER['REQUEST_URI'];
$ext = pathinfo($input, PATHINFO_EXTENSION);

$file = ($ext === 'zip' ? $config->distdir : $config->cachedir) . $input;

if (in_array($ext, ['zip', 'json'])) {
    if (file_exists($file)) {
        $distUrl = file_get_contents($file);
    } else {
        $distUrl = rtrim($config->packagistUrl, '/')."/".ltrim($input, '/');
    }

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $distUrl);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $contents = curl_exec($ch);
        curl_close($ch);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($distUrl).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($contents));
        echo $contents;
    } catch (\Exception $e) {
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . $distUrl);
    }
    exit();
} else {
?>
<!doctype html>
<html lang="en-US">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>404 - 对不起，您访问的页面不存在！</title>
    <style>a, body, div, h1, h2, html, p, span {
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 100%;
            font: inherit;
            vertical-align: baseline;
            outline: 0
        }

        body {
            background: #f2f2f2;
            font-family: "Microsoft YaHei", Helvetica, Arial, Lucida Grande, Tahoma, sans-serif;
            -webkit-font-smoothing: antialiased;
            overflow: hidden
        }

        .right {
            float: right
        }

        #main {
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding-top: 8%
        }

        h1 {
            position: relative;
            display: block;
            font: 72px "Microsoft YaHei", Helvetica, Arial, Lucida Grande, Tahoma, sans-serif;
            color: #16a085;
            text-shadow: 2px 2px #f7f7f7;
            text-align: center
        }

        .sub {
            position: relative;
            font-size: 21px;
            top: -20px;
            padding: 0 10px;
            font-style: italic
        }

        @media screen and (max-width: 374px) {
            .sub {
                display: none
            }
        }

        .icon {
            position: relative;
            display: inline-block;
            top: -6px;
            margin: 0 10px 5px 0;
            background: #16a085;
            width: 50px;
            height: 50px;
            -moz-box-shadow: 1px 2px #fff;
            -webkit-box-shadow: 1px 2px #fff;
            box-shadow: 1px 2px #fff;
            -webkit-border-radius: 50px;
            -moz-border-radius: 50px;
            border-radius: 50px;
            color: #dfdfdf;
            font-size: 46px;
            line-height: 48px;
            font-weight: 700;
            text-align: center;
            text-shadow: 0 0
        }

        #content {
            position: relative;
            width: 100%;
            max-width: 600px;
            background: #fff;
            -webkit-border-radius: 5px;
            -moz-border-radius: 5px;
            border-radius: 5px;
            z-index: 5
        }

        h2 {
            background-position: bottom;
            padding: 20px 0 22px 0;
            font: 20px "Microsoft YaHei", Helvetica, Arial, Lucida Grande, Tahoma, sans-serif;
            text-align: center
        }

        p {
            position: relative;
            padding: 20px;
            font-size: 14px;
            line-height: 25px
        }

        .utilities {
            padding: 0 20px 50px
        }

        .utilities .button {
            display: inline-block;
            height: 34px;
            margin: 0 0 0 6px;
            padding: 0 18px;
            background: #16a085;
            -webkit-border-radius: 3px;
            -moz-border-radius: 3px;
            border-radius: 3px;
            font-size: 14px;
            line-height: 34px;
            color: #fff;
            font-weight: 700;
            text-decoration: none
        }</style>
</head>
<body>
<div id="wrapper">
    <div id="main">
        <header id="header"><h1><span class="icon">!</span>404<span class="sub">page not found</span></h1></header>
        <div id="content"><h2>对不起，您访问的页面不存在！</h2>
            <p>当您看到这个页面，表示您想访问的页面不存在，请确认您输入的地址是正确的。</p>
            <div class="utilities">
                <a class="button right" href="https://laravel-china.org/composer">镜像帮助</a>
                <div class="clear"></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php } ?>

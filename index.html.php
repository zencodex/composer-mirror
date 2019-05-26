<?php
    require __DIR__ . '/vendor/autoload.php';
    use cebe\markdown\GithubMarkdown;
    $source = file_get_contents(__DIR__ . '/README.md');
    $parser = new GithubMarkdown();
    $readme = $parser->parse($source);
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>Composer国内镜像 | ZComposer 全量镜像开源</title>
    <meta name="author" content="扣丁禅师<v@yinqisen.cn>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.bootcss.com/twitter-bootstrap/3.3.5/css/bootstrap.min.css" />
    <style>
        html,
        body {
            height: 100%;
            background: #FAFAFA;
        }

        .wrapper .container {
            padding: 26px 20px;
            color: #2d2d32;
        }

        .content p {
            font-size: 14px;
            margin-top: 10px;
            line-height: 22px;
        }

        pre,
        code {
            background: #fff;
            font-family: "Source Code Pro", Menlo, Consolas, "Courier New", Courier, monospace;
            border-radius: 0;
            border: none;
            color: #2d2d32;
            line-height: 12px;
            font-size: 12px;
        }

        pre {
            border-left: 2px solid #f28d1a;
        }

        .info-header {
            padding-top: 20px;
            text-align: center;
        }

        hr {
            border-color: #F28D1A;
        }

        .wrap {
            min-height: 100%;
            height: auto;
            margin: 0 auto -55px;
            padding: 0 0 55px;
            position: relative;
        }

        .wrapper-footer {
            background: #2d2d32;
            font-size: 14px;
            line-height: 16px;
            padding: 35px 0 20px;
        }

        footer p {
            float: left;
            color: #c3c3c3;
        }

        .wrapper-footer ul {
            list-style: none;
        }

        .wrapper-footer li {
            padding-bottom: 8px;
        }

        .wrapper-footer a {
            color: #fff;
        }

        .wrapper-footer .social {
            font-size: 26px;
        }

        .wrapper-footer .social li {
            float: right;
            margin-left: 15px;
        }

        .info-header .title {
            font-size: 16px;
            font-weight: 600;
            color: #2d2d32;
        }
    </style>
</head>

<body>
    <section class="wrap">
        <section class="container content" role="main">
            <div class="info-header">
                <span class="title">镜像最后更新时间：</span>
                <span class="release-date"><?php echo $update_at; ?></span>
            </div>
            <hr>
            <?php echo $readme; ?>
        </section>
    </section>
    <footer class="wrapper-footer">
        <nav class="container">
        </nav>
    </footer>
    <script>
        var _hmt = _hmt || [];
        (function () {
            var hm = document.createElement("script");
            hm.src = "https://hm.baidu.com/hm.js?642df4b4cce6054913720f58f84318ce";
            var s = document.getElementsByTagName("script")[0];
            s.parentNode.insertBefore(hm, s);
        })();
    </script>
</body>

</html>
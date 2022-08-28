<?php
if (!$_SERVER['REQUEST_METHOD'] || !in_array(strtoupper($_SERVER['REQUEST_METHOD']), ['POST', 'GET'])) {
    $error = "Request Method Not Allow";
    goto error;
}

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    goto static_page;
}

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../config.php')) {
    $config = require __DIR__ . '/../config.php';
} else {
    $config = require __DIR__ . '/../config.default.php';
}

if (strtolower($_SERVER['CONTENT_TYPE']) == 'application/json') {
    $data = file_get_contents('php://input');

    $data = json_decode($data, true);
    if (!$data) {
        $error = "Paramter must be an valid json data";
        goto error;
    }

    if (empty($data['packages'])) {
        $error = "Paramter packages must be provider";
        goto error;
    }

    $packages = $data['packages'] ?? [];
} else {
    if (empty($_POST['packages'])) {
        $error = "Paramter packages must be provider";
        goto error;
    }

    $packages = (array) ($_POST['packages'] ?? []);
}

if (empty($packages)) {
    $error = "Paramter packages cannot be empty";
    goto error;
}

if (!is_array($packages)) {
    $error = "Paramter packages must be an array";
    goto error;
}

try {
    $packagesData = json_encode($packages);

    $submitPackages = json_decode($packagesData, true);
    if (!$submitPackages) {
        $error = "Submit packages is invalid json data";
        goto error;
    }

    $cacheFile = rtrim($config->cachedir, '/') . "/current_sync_packages.php";

    $currentSyncPackages = [];
    if (file_exists($cacheFile)) {
        $currentSyncPackages = include $cacheFile;
    }
    if (!is_array($currentSyncPackages)) {
        $currentSyncPackages = [];
    }

    $syncPackages = array_merge($currentSyncPackages, $submitPackages);
    $syncPackages = array_values(array_unique($syncPackages));

    file_put_contents($cacheFile, "<?php return " . var_export($syncPackages, true) . ";");
} catch (\Throwable $e) {
    $error = "Submit packages error: " . $e->getMessage();
    goto error;
}

$accept = getallheaders()['Accept'] ?? '';
if (strtolower($accept) == 'application/json') {
    header("Content-Type: application/json");

    if (!empty($error)) {
        error:
        echo json_encode([
            'error' => $error,
        ]);
        return;
    } else {
        success:
        echo json_encode([
            'message' => 'success',
        ]);
        return;
    }
} else {
    echo "<h1>提交成功</h1>";
    return;
}

static_page:
?>

<!doctype html>
<html lang="en-US">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>扩展包提交</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.0/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.0/js/bootstrap.min.js"></script>

    <style>
        a,
        body,
        div,
        h1,
        h2,
        html,
        p,
        span {
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
        }
    </style>
</head>

<body>
    <div id="wrapper">
        <div id="main">
            <header id="header">
                <h1>同步扩展包<br><span class="sub">提交需要进行镜像同步的扩展包</span></h1>
            </header>
            <div id="content" class="container p-4">
                <form class="row g-3" action="" method="post">
                    <div class="row mb-3 pt-4">
                        <label for="packages[0]" class="col-sm-2 col-form-label">扩展包</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="packages[0]" placeholder="vendor/package">
                        </div>
                    </div>

                    <div class="col-sm-10 offset-sm-2">
                        <button type="submit" class="btn btn-primary">提交</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
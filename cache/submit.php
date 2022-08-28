<?php
    if (!$_SERVER['REQUEST_METHOD'] || !in_array(strtoupper($_SERVER['REQUEST_METHOD']), ['POST'])) {
        $error = "Request Method Not Allow";
        goto error;
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

        $cacheFile = rtrim($config->cachedir, '/')."/current_sync_packages.php";
        
        $currentSyncPackages = [];
        if (file_exists($cacheFile)) {
            $currentSyncPackages = include $cacheFile;
        }
        if (!is_array($currentSyncPackages)) {
            $currentSyncPackages = [];
        }

        $syncPackages = array_merge($currentSyncPackages, $submitPackages);
        $syncPackages = array_values(array_unique($syncPackages));

        file_put_contents($cacheFile, "<?php return ".var_export($syncPackages, true).";");
    } catch (\Throwable $e) {
        $error = "Submit packages error: " . $e->getMessage();
        goto error;
    }

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

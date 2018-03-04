<?php

const BASEPATH = 'cache';
const OPTPATH = 'optimized';

$packagesjson = json_decode(file_get_contents(BASEPATH . '/packages.json'));

if (file_exists('optimize.db')) {
    unlink('optimize.db');
}

$pdo = new PDO('sqlite:optimize.db', null, null, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS providers ('
    .'file TEXT'
    .',hash TEXT'
    .')'
);
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS packages ('
    .'provider TEXT'
    .',file TEXT'
    .',hash TEXT'
    .')'
);
$pdo->beginTransaction();

$muda = 0;
foreach ($packagesjson->{'provider-includes'} as $providerpath => $providerinfo) {
    $providerjson = json_decode(file_get_contents(BASEPATH . '/' . str_replace('%hash%', $providerinfo->sha256, $providerpath)));

    foreach ($providerjson->providers as $packagename => $packageinfo) {
        $packagejson = json_decode(file_get_contents(BASEPATH . "/p/$packagename\${$packageinfo->sha256}.json"), true);

        foreach ($packagejson['packages'] as $versionname => $info) {
            if ($versionname !== $packagename) {
                echo "むだな $versionname が $packagename の中に含まれています\n";
                $muda += strlen(json_encode($info));
                unset($packagejson['packages'][$versionname]);
            }
        }

        if (empty($packagejson['packages'])) {
            echo "$packagename は パッケージ情報を何も含んでいません。。要らないんじゃね？\n";
            continue;
        }

        // package.jsonを作りなおす
        $packagestr = json_encode($packagejson, JSON_UNESCAPED_SLASHES);
        $packagehash = hash('sha256', $packagestr);

        // 新しいファイルとして書き出し
        $path = OPTPATH . "/p/{$packagename}\${$packagehash}.json";
        $dir = dirname($path);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $packagestr);

        // DB上のhash値を更新
        $stmt = $pdo->prepare('INSERT INTO packages (provider, file, hash) VALUES (:provider, :file, :hash)');
        $stmt->bindValue(':provider', $providerpath);
        $stmt->bindValue(':file', $packagename);
        $stmt->bindValue(':hash', $packagehash);
        $stmt->execute();
    }

    // provider.jsonを作りなおす
    // DBから結果的にどうなったか抽出する
    $stmt = $pdo->prepare('SELECT file, hash FROM packages WHERE provider = :provider');
    $stmt->bindValue(':provider', $providerpath);
    $stmt->execute();
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $newpackages = [];
    foreach ($stmt as $row) {
        $newpackages[$row['file']] = ['sha256'=>$row['hash']];
    }
    
    $providerjson = ['providers' => $newpackages];
    $providerstr = json_encode($providerjson, JSON_UNESCAPED_SLASHES);
    $providerhash = hash('sha256', $providerstr);

    // 新しいファイルとして書き出し
    $path = OPTPATH . '/' . str_replace('%hash%', $providerhash, $providerpath);
    $dir = dirname($path);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($path, $providerstr);

    // DB上のhash値を更新
    $stmt = $pdo->prepare('INSERT INTO providers (file, hash) VALUES (:file, :hash)');
    $stmt->bindValue(':file', $providerpath);
    $stmt->bindValue(':hash', $providerhash);
    $stmt->execute();
}
$pdo->commit();

// 最後にpackages.jsonを作る
// DBから結果的にどうなったか抽出する
$stmt = $pdo->query('SELECT file, hash FROM providers');
$stmt->setFetchMode(PDO::FETCH_ASSOC);
$newproviders = [];
foreach ($stmt as $row) {
    $newproviders[$row['file']] = ['sha256'=>$row['hash']];
}
$packagesjson->{'provider-includes'} = $newproviders;
$packagesstr = json_encode($packagesjson, JSON_UNESCAPED_SLASHES);

// 新しいファイルとして書き出し
$path = OPTPATH . '/packages.json';
file_put_contents($path, $packagesstr);


echo "全部で $muda byte 無駄です\n";

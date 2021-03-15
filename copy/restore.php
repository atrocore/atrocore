<?php

if (substr(php_sapi_name(), 0, 3) != 'cli') {
    header("HTTP/1.0 404 Not Found");
    exit();
}

// change directory
chdir(dirname(__FILE__));

// set the include_path
set_include_path(dirname(__FILE__));

if (file_exists('dump')) {
    echo 'Restoring files...' . PHP_EOL;

    foreach (['data', 'client', 'custom', 'vendor'] as $dir) {
        exec('cp -R dump/' . $dir . '/ .', $output, $result);
    }

    file_put_contents('data/stable-composer.json', file_get_contents('composer.json'));
    file_put_contents('data/previous-composer.lock', file_get_contents('composer.lock'));

    echo 'Done!' . PHP_EOL;

    echo 'Restoring database...' . PHP_EOL;

    if (!file_exists('data/config.php') || !file_exists('dump/db.sql')) {
        echo 'Failed!' . PHP_EOL;
        exit(1);
    }

    $config = include 'data/config.php';

    if (empty($config['database'])) {
        echo 'Failed!' . PHP_EOL;
        exit(1);
    }

    $db = $config['database'];

    $port = empty($db['port']) ? '' : "port={$db['port']};";

    $options = [];
    if (isset($db['sslCA'])) {
        $options[\PDO::MYSQL_ATTR_SSL_CA] = $db['sslCA'];
    }
    if (isset($db['sslCert'])) {
        $options[\PDO::MYSQL_ATTR_SSL_CERT] = $db['sslCert'];
    }
    if (isset($db['sslKey'])) {
        $options[\PDO::MYSQL_ATTR_SSL_KEY] = $db['sslKey'];
    }
    if (isset($db['sslCAPath'])) {
        $options[\PDO::MYSQL_ATTR_SSL_CAPATH] = $db['sslCAPath'];
    }
    if (isset($db['sslCipher'])) {
        $options[\PDO::MYSQL_ATTR_SSL_CIPHER] = $db['sslCipher'];
    }

    $pdo = new \PDO("Mysql:host={$db['host']};{$port}dbname={$db['dbname']};charset={$db['charset']}", $db['user'], $db['password'], $options);

    $pdo->exec(file_get_contents('dump/db.sql'));

    echo 'Done!' . PHP_EOL;

    echo 'Removing dump...' . PHP_EOL;
    exec('rm -R dump');
    echo 'Done!' . PHP_EOL;

    exit(0);
}

echo 'No dump data!' . PHP_EOL;
exit(0);


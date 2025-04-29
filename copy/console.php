<?php
if (substr(php_sapi_name(), 0, 3) != 'cli') {
    echo 'For CLI only!' . PHP_EOL;
    exit(1);
}

chdir(dirname(__FILE__));
set_include_path(dirname(__FILE__));

require_once 'vendor/autoload.php';

$app = new \Atro\Core\Application();
$app->runConsole($argv);

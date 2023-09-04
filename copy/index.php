<?php
chdir(dirname(__FILE__));
set_include_path(dirname(__FILE__));

require_once 'vendor/autoload.php';

$app = new \Atro\Core\Application();

if (substr(php_sapi_name(), 0, 3) == 'cli') {
    $app->runConsole($argv);
} else {
    $app->run();
}

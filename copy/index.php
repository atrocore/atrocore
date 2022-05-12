<?php
// change directory
chdir(dirname(__FILE__));

// set the include_path
set_include_path(dirname(__FILE__));

// autoload
require_once 'vendor/autoload.php';

// create app
$app = new \Espo\Core\Application();

// run
if (substr(php_sapi_name(), 0, 3) == 'cli') {
    $app->runConsole($argv);
} else {
    $app->run();
}

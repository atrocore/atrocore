<?php

// change directory
chdir(dirname(__FILE__));

// set the include_path
set_include_path(dirname(__FILE__));

// define global variables
define('COMPOSER_LOG', 'data/treo-composer.log');

/** @var bool $isCli */
$isCli = substr(php_sapi_name(), 0, 3) == 'cli';

/**
 * Read composer logs file and return json for real time logs
 */
if (!$isCli && !empty($_GET['composerLogs'])) {
    header('Content-Type: application/json');
    echo \json_encode(!file_exists(COMPOSER_LOG) ? ['status' => false] : ['status' => true, 'logs' => file_get_contents(COMPOSER_LOG)]);
    die();
}

// autoload
require_once 'vendor/autoload.php';

// create app
$app = new \Treo\Core\Application();

// run
if (!$isCli) {
    $app->run();
} else {
    $app->runConsole($argv);
}

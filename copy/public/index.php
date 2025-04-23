<?php
chdir(dirname(dirname(__FILE__)));
set_include_path(dirname(dirname(__FILE__)));

require_once 'vendor/autoload.php';

$app = new \Atro\Core\Application();
$app->run();

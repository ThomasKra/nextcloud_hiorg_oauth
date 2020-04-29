<?php

require __DIR__ . '/../3rdparty/autoload.php';

$app = \OC::$server->query(OCA\HiorgOAuth\AppInfo\Application::class);
$app->register();

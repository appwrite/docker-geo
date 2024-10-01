<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Appwrite\Geo\Server\Server;

ini_set('memory_limit', '-1');

$server = new Server();

$server->start();

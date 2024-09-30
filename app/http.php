<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Appwrite\Geo\Platform\Geo;
use Appwrite\Geo\Server\Server;
use Utopia\Platform\Service;

ini_set('memory_limit', '-1');

$server = new Server();

$server->start();
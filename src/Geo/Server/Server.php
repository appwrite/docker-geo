<?php

namespace Appwrite\Geo\Server;

use Appwrite\Geo\Platform\Geo;
use Utopia\CLI\Console;
use Utopia\DI\Container;
use Utopia\DI\Dependency;
use Utopia\Http\Adapter\Swoole\Server as SwooleServer;
use Utopia\Http\Http;
use MaxMind\Db\Reader;
use Utopia\Http\Request;
use Utopia\Platform\Service;

class Server
{
    protected Http $http;
    public function __construct(?Http $http = null)
    {
        $http ??= new Http(new SwooleServer('0.0.0.0', '80', [

        ]), new Container(), 'UTC');
        $this->http = $http;

        $this->initResources();
        $this->initHooks();
        $this->initPlatform();
    }

    protected function initHooks()
    {
        $onStart = $this->http->onStart();
        $onStart->setCallback(function() {
            Console::log('Server started');
        });
    }

    protected function initResources()
    {

        $container = $this->http->getContainer();

        $geodb = new Dependency();
        $geodb->setName('geodb');
        $geodb->setCallback(function() {
            return new Reader(__DIR__ . '/assets/dbip/dbip-country-lite-2024-09.mmdb');
        });

        $container->set($geodb);
    }

    protected function initPlatform()
    {
        $platform = new Geo();
        $platform->init(Service::TYPE_HTTP);
    }

    public function start()
    {
        $this->http->start();
    }
}
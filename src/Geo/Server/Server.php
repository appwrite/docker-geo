<?php

namespace Appwrite\Geo\Server;

use Appwrite\Geo\Platform\Geo;
use Exception;
use Utopia\CLI\Console;
use Utopia\DI\Container;
use Utopia\DI\Dependency;
use Utopia\DSN\DSN;
use Utopia\Http\Adapter\Swoole\Server as SwooleServer;
use Utopia\Http\Http;
use MaxMind\Db\Reader;
use Throwable;
use Utopia\Logger\Adapter\AppSignal;
use Utopia\Logger\Adapter\LogOwl;
use Utopia\Logger\Adapter\Raygun;
use Utopia\Logger\Adapter\Sentry;
use Utopia\Logger\Log;
use Utopia\Logger\Logger;
use Utopia\Platform\Service;
use Utopia\System\System;

class Server
{
    protected Http $http;
    public function __construct(?Http $http = null)
    {
        $http ??= new Http(new SwooleServer('0.0.0.0', '80', [

        ]), new Container(), 'UTC');
        $this->http = $http;

        $this->http->setMode(System::getEnv('GEO_ENV', Http::MODE_TYPE_PRODUCTION));

        $this->initResources();
        $this->initHooks();
        $this->initPlatform();
    }

    protected function initHooks(): void
    {

        $onStart = Http::onStart();
        $onStart->setCallback(function () {
            Console::log('Server started');
        });
    }

    protected function initResources(): void
    {

        $container = $this->http->getContainer();

        $geodb = new Dependency();
        $geodb->setName('geodb');
        $geodb->setCallback(function () {
            /** @phpstan-ignore class.notFound */
            return new Reader(__DIR__ . '/../../../app/assets/dbip/dbip-country-lite-2024-09.mmdb');
        });

        $container->set($geodb);

        $logger = new Dependency();
        $logger->setName('logger');

        /**
         * Create logger
         */
        $logger->setCallback(function () {
            $providerName = System::getEnv('GEO_LOGGING_PROVIDER', '');
            $providerConfig = System::getEnv('GEO_LOGGING_CONFIG', '');

            try {
                $loggingProvider = new DSN($providerConfig ?? '');

                $providerName = $loggingProvider->getScheme();
                $providerConfig = match ($providerName) {
                    'sentry' => ['key' => $loggingProvider->getPassword(), 'projectId' => $loggingProvider->getUser() ?? '', 'host' => 'https://' . $loggingProvider->getHost()],
                    'logowl' => ['ticket' => $loggingProvider->getUser() ?? '', 'host' => $loggingProvider->getHost()],
                    default => ['key' => $loggingProvider->getHost()],
                };
            } catch (Throwable) {
                $configChunks = \explode(";", ($providerConfig ?? ''));

                $providerConfig = match ($providerName) {
                    'sentry' => ['key' => $configChunks[0], 'projectId' => $configChunks[1] ?? '', 'host' => '',],
                    'logowl' => ['ticket' => $configChunks[0] ?? '', 'host' => ''],
                    default => ['key' => $providerConfig],
                };
            }

            $logger = null;

            if (!empty($providerName) && is_array($providerConfig) && Logger::hasProvider($providerName)) {
                $adapter = match ($providerName) {
                    'sentry' => new Sentry($providerConfig['projectId'] ?? '', $providerConfig['key'] ?? '', $providerConfig['host'] ?? ''),
                    'logowl' => new LogOwl($providerConfig['ticket'] ?? '', $providerConfig['host'] ?? ''),
                    'raygun' => new Raygun($providerConfig['key'] ?? ''),
                    'appsignal' => new AppSignal($providerConfig['key'] ?? ''),
                    default => throw new Exception('Provider "' . $providerName . '" not supported.')
                };

                $logger = new Logger($adapter);
            }

            return $logger;
        });

        $log = new Dependency();
        $log->setName('log');
        $log->setCallback(fn () => new Log());

        $container->set($logger);
        $container->set($log);


    }

    protected function initPlatform(): void
    {
        $platform = new Geo();
        $platform->init(Service::TYPE_HTTP);
    }

    public function start(): void
    {
        $this->http->start();
    }
}

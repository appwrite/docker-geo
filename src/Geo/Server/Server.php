<?php

namespace Appwrite\Geo\Server;

use Appwrite\Geo\Platform\Geo;
use Exception;
use InvalidArgumentException;
use MaxMind\Db\Reader;
use Throwable;
use Utopia\Console;
use Utopia\DI\Container;
use Utopia\DSN\DSN;
use Utopia\Http\Adapter\Swoole\Server as SwooleServer;
use Utopia\Http\Http;
use Utopia\Http\Request;
use Utopia\Platform\Service;
use Utopia\Span\Exporter\Sentry;
use Utopia\Span\Exporter\SentryField;
use Utopia\Span\Exporter\Stdout;
use Utopia\Span\Span;
use Utopia\Span\Storage\Coroutine;
use Utopia\System\System;

class Server
{
    protected Http $http;

    protected Container $resources;

    public function __construct(?Http $http = null)
    {
        $this->resources = new Container();

        Http::setMode(System::getEnv('GEO_ENV', Http::MODE_TYPE_PRODUCTION));

        $this->initSpan();
        $this->initResources();

        $http ??= new Http(
            new SwooleServer(
                host: '0.0.0.0',
                port: '80',
                settings: [
                    'enable_coroutine' => true,
                ],
                resources: $this->resources,
            ),
            'UTC'
        );
        $this->http = $http;

        $this->initHooks();
        $this->initPlatform();
    }

    protected function initHooks(): void
    {
        $onStart = Http::onStart();
        $onStart->action(function () {
            Console::log('Server started');
        });

        Http::onRequest()
            ->inject('request')
            ->action(function (Request $request) {
                Span::init('http.request');
                Span::add('http.method', $request->getMethod());
            });

        Http::shutdown()
            ->groups(['*'])
            ->action(fn () => Span::current()?->finish());
    }

    protected function initResources(): void
    {
        $this->resources->set('geodb', function () {
            $defaultPath = __DIR__ . '/../../../app/assets/dbip/dbip-country-lite-2026-08.mmdb';
            $path = System::getEnv('GEO_DBIP_PATH', $defaultPath);
            if (!\is_readable($path)) {
                throw new Exception('GeoIP database file not found or not readable: ' . $path);
            }
            return new Reader($path);
        });
    }

    protected function initSpan(): void
    {
        Span::setStorage(new Coroutine());

        // Server failures only, as the error handler printed before spans
        $sampler = static fn (Span $span): bool => $span->getError() !== null && $span->get('error.publish') !== false;

        $exporters = [new Stdout(sampler: $sampler)];

        // GEO_LOGGING_CONFIG: a sentry://PROJECT_ID:KEY@HOST DSN reports server errors to Sentry
        $config = System::getEnv('GEO_LOGGING_CONFIG', '');
        if (!empty($config)) {
            try {
                $dsn = new DSN($config);
                if ($dsn->getScheme() !== 'sentry') {
                    throw new InvalidArgumentException('Only the sentry:// scheme is supported');
                }

                $tags = ['http.method', 'http.path', 'error.type', 'error.code'];
                $version = System::getEnv('GEO_VERSION', '');
                $exporters[] = new Sentry(
                    sampler: static fn (Span $span): bool => $span->get('error.publish') !== false,
                    dsn: 'https://' . $dsn->getPassword() . '@' . $dsn->getHost() . '/' . $dsn->getUser(),
                    environment: Http::isProduction() ? 'production' : 'staging',
                    release: empty($version) ? 'UNKNOWN' : $version,
                    serverName: \gethostname() ?: null,
                    classifier: static fn (string $key): SentryField => \in_array($key, $tags, true) ? SentryField::Tag : SentryField::Context,
                );
            } catch (Throwable $error) {
                Console::error('Invalid GEO_LOGGING_CONFIG, error reporting is disabled: ' . $error->getMessage());
            }
        }

        Span::setExporters(...$exporters);
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

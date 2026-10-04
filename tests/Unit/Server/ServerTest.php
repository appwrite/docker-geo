<?php

namespace Tests\Unit\Server;

use Appwrite\Geo\Server\Server;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Unit\Response;
use Utopia\DI\Container;
use Utopia\Http\Adapter\FPM\Request;
use Utopia\Http\Adapter\FPM\Server as FPMServer;
use Utopia\Http\Http;
use Utopia\Psr7\Response as Psr7Response;
use Utopia\Span\Span;

use function Swoole\Coroutine\run;

/**
 * Boots the server with its own exporters and runs requests through its hooks,
 * capturing what the Sentry exporter delivers over HTTP.
 */
class ServerTest extends TestCase
{
    private const string SECRET = 'test-secret';

    /**
     * @var array<RequestInterface>
     */
    private array $delivered = [];

    private Http $http;

    protected function setUp(): void
    {
        Http::reset();
        \putenv('GEO_SECRET=' . self::SECRET);
        \putenv('GEO_DBIP_PATH=/missing.mmdb');
        \putenv('GEO_LOGGING_CONFIG=sentry://123:public-key@sentry.example.com');

        $this->delivered = [];
        $client = new class ($this->delivered) implements ClientInterface {
            /**
             * @param array<RequestInterface> $delivered
             */
            public function __construct(private array &$delivered)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->delivered[] = $request;
                return new Psr7Response(200);
            }
        };

        $this->http = new Http(new FPMServer(new Container()), 'UTC');
        new Server($this->http, $client);
    }

    protected function tearDown(): void
    {
        Http::reset();
        Span::setExporters();
        Span::setStorage(null);
        \putenv('GEO_SECRET');
        \putenv('GEO_DBIP_PATH');
        \putenv('GEO_LOGGING_CONFIG');
    }

    public function testServerErrorIsDeliveredToSentry(): void
    {
        $status = $this->request('/v1/ips/1.1.1.1', 'Bearer ' . self::SECRET);

        $this->assertSame(500, $status);
        $this->assertCount(1, $this->delivered);
        $this->assertSame('https://sentry.example.com/api/123/envelope/', (string) $this->delivered[0]->getUri());

        $lines = \explode("\n", (string) $this->delivered[0]->getBody());
        $event = \json_decode($lines[2], true);
        $this->assertIsArray($event);
        $this->assertSame('GeoIP database file not found or not readable: /missing.mmdb', $event['message']);
        $this->assertSame('Exception', $event['exception']['values'][0]['type']);
        $this->assertSame('/v1/ips/:ip', $event['tags']['http.path']);
    }

    public function testClientErrorIsNotDelivered(): void
    {
        $status = $this->request('/v1/ips/1.1.1.1', 'Bearer wrong-secret');

        $this->assertSame(401, $status);
        $this->assertSame([], $this->delivered);
    }

    public function testNotFoundIsNotDelivered(): void
    {
        $status = $this->request('/v1/missing');

        $this->assertSame(404, $status);
        $this->assertSame([], $this->delivered);
    }

    public function testSuccessfulRequestIsNotDelivered(): void
    {
        $status = $this->request('/v1/health');

        $this->assertSame(200, $status);
        $this->assertSame([], $this->delivered);
    }

    /**
     * Run one request through the server's hooks inside a coroutine, as Swoole does.
     */
    private function request(string $uri, ?string $authorization = null): int
    {
        $response = new Response();

        run(function () use ($uri, $authorization, $response) {
            $request = (new Request())->setMethod('GET')->setURI($uri);
            if ($authorization !== null) {
                $request->setHeader('authorization', $authorization);
            }

            $this->http->run($request, $response);
        });

        return $response->getStatusCode();
    }
}

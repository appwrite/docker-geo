<?php

namespace Tests\Unit\Modules\Core\Http;

use Appwrite\Geo\Modules\Core\Http\Error;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Response;
use Utopia\Http\Route;
use Utopia\Span\Exporter\Exporter;
use Utopia\Span\Span;
use Utopia\Span\Storage\Memory;

class ErrorTest extends TestCase
{
    /**
     * @var array<Span>
     */
    private array $exported = [];

    protected function setUp(): void
    {
        $this->exported = [];

        $exporter = new class ($this->exported) implements Exporter {
            /**
             * @param array<Span> $exported
             */
            public function __construct(private array &$exported)
            {
            }

            public function export(Span $span): void
            {
                $this->exported[] = $span;
            }

            public function sample(Span $span): bool
            {
                return true;
            }
        };

        Span::setStorage(new Memory());
        Span::setExporters($exporter);
    }

    protected function tearDown(): void
    {
        Span::setExporters();
        Span::setStorage(null);
    }

    public function testServerErrorIsExportedWithItsThrowable(): void
    {
        $error = new Exception('GeoIP database file not found or not readable: /missing.mmdb');
        $response = $this->response();

        Span::init('http.request');
        (new Error())->action(new Route('GET', '/v1/ips/:ip'), $error, $response);

        $this->assertCount(1, $this->exported);
        $span = $this->exported[0];
        $this->assertSame($error, $span->getError());
        $this->assertSame('error', $span->get('level'));
        $this->assertTrue($span->get('error.publish'));
        $this->assertSame(Exception::class, $span->get('error.type'));
        $this->assertSame(0, $span->get('error.code'));
        $this->assertSame('GET', $span->get('http.method'));
        $this->assertSame('/v1/ips/:ip', $span->get('http.path'));
        $this->assertNull(Span::current());
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testClientErrorIsMarkedUnpublished(): void
    {
        $error = new Exception('Invalid Geo server key', 401);
        $response = $this->response();

        Span::init('http.request');
        (new Error())->action(new Route('GET', '/v1/ips/:ip'), $error, $response);

        $this->assertCount(1, $this->exported);
        $this->assertSame($error, $this->exported[0]->getError());
        $this->assertFalse($this->exported[0]->get('error.publish'));
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testErrorWithoutRequestSpanIsStillExported(): void
    {
        $error = new Exception('Not Found', 404);

        (new Error())->action(null, $error, $this->response());

        $this->assertCount(1, $this->exported);
        $this->assertSame($error, $this->exported[0]->getError());
        $this->assertNull($this->exported[0]->get('http.path'));
    }

    private function response(): Response
    {
        return new Response();
    }
}

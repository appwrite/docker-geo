<?php

namespace Tests\Unit;

use Appwrite\Geo\GeoIp;
use Appwrite\Geo\GeoRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Utopia\Fetch\Client as FetchClient;
use Utopia\Fetch\Response;

final class GeoIpTest extends TestCase
{
    public function testGetReturnsGeoRecord(): void
    {
        $client = $this->createMock(FetchClient::class);
        $client
            ->expects($this->exactly(2))
            ->method('addHeader')
            ->willReturnCallback(function (string $key, string $value) use ($client): FetchClient {
                if ($key === 'Authorization') {
                    $this->assertSame('Bearer secret', $value);
                    return $client;
                }

                $this->assertSame('Accept', $key);
                $this->assertSame(FetchClient::CONTENT_TYPE_APPLICATION_JSON, $value);

                return $client;
            });
        $client
            ->expects($this->once())
            ->method('setTimeout')
            ->with(1000)
            ->willReturn($client);
        $client
            ->expects($this->once())
            ->method('fetch')
            ->with('http://geo/v1/ips/8.8.8.8', FetchClient::METHOD_GET)
            ->willReturn(new Response(200, '{"countryCode":"US","city":{"en":"Mountain View"}}', []));

        $geo = new GeoIp('http://geo/', 'secret', $client);
        $record = $geo->get('8.8.8.8');

        $this->assertInstanceOf(GeoRecord::class, $record);
        $this->assertSame('US', $record->getCountryCode());
        $this->assertSame(['en' => 'Mountain View'], $record->get('city'));
        $this->assertSame([
            'countryCode' => 'US',
            'city' => [
                'en' => 'Mountain View',
            ],
        ], $record->toArray());
    }

    public function testGetCountryCodeReturnsCountryCode(): void
    {
        $geo = new GeoIp('http://geo', 'secret', $this->clientReturning(new Response(200, '{"countryCode":"US"}', [])));

        $this->assertSame('US', $geo->getCountryCode('8.8.8.8'));
    }

    public function testGetCountryCodeReturnsNullWhenCountryCodeIsMissing(): void
    {
        $geo = new GeoIp('http://geo', 'secret', $this->clientReturning(new Response(200, '{}', [])));

        $this->assertNull($geo->getCountryCode('8.8.8.8'));
    }

    public function testGetReturnsNullOnHttpErrors(): void
    {
        $geo = new GeoIp('http://geo', 'secret', $this->clientReturning(new Response(500, '{}', [])));

        $this->assertNull($geo->get('8.8.8.8'));
    }

    public function testGetReturnsNullOnInvalidJson(): void
    {
        $geo = new GeoIp('http://geo', 'secret', $this->clientReturning(new Response(200, 'not-json', [])));

        $this->assertNull($geo->get('8.8.8.8'));
    }

    public function testGetReturnsNullOnFetchExceptions(): void
    {
        $client = $this->createStub(FetchClient::class);
        $client
            ->method('addHeader')
            ->willReturn($client);
        $client
            ->method('setTimeout')
            ->willReturn($client);
        $client
            ->method('fetch')
            ->willThrowException(new RuntimeException('timeout'));

        $geo = new GeoIp('http://geo', 'secret', $client);

        $this->assertNull($geo->get('8.8.8.8'));
    }

    public function testGetDoesNotCallClientWithoutEndpointOrSecret(): void
    {
        $client = $this->createMock(FetchClient::class);
        $client
            ->method('addHeader')
            ->willReturn($client);
        $client
            ->method('setTimeout')
            ->willReturn($client);
        $client
            ->expects($this->never())
            ->method('fetch');

        $this->assertNull(new GeoIp('', 'secret', $client)->get('8.8.8.8'));
        $this->assertNull(new GeoIp('http://geo', '', $client)->get('8.8.8.8'));
    }

    private function clientReturning(Response $response): FetchClient
    {
        $client = $this->createStub(FetchClient::class);
        $client
            ->method('addHeader')
            ->willReturn($client);
        $client
            ->method('setTimeout')
            ->willReturn($client);
        $client
            ->method('fetch')
            ->willReturn($response);

        return $client;
    }
}

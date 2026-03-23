<?php

namespace Tests\E2E;

class GeoTest extends Base
{
    public function testGetIpRequiresAuth(): void
    {
        $response = $this->getJson('/v1/ips/8.8.8.8');

        $this->assertEquals(401, $response['status']);
    }

    public function testGetIpRejectsInvalidAuthScheme(): void
    {
        $response = $this->getJson('/v1/ips/8.8.8.8', [
            'Authorization' => 'Basic ' . $this->secret,
        ]);

        $this->assertEquals(401, $response['status']);
    }

    public function testGetIpRejectsInvalidSecret(): void
    {
        $response = $this->getJson('/v1/ips/8.8.8.8', [
            'Authorization' => 'Bearer wrong-secret',
        ]);

        $this->assertEquals(401, $response['status']);
    }

    public function testGetIpReturnsGeoData(): void
    {
        $response = $this->getJson('/v1/ips/8.8.8.8', [
            'Authorization' => 'Bearer ' . $this->secret,
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertEquals('8.8.8.8', $response['json']['ip']);
        $this->assertArrayHasKey('countryCode', $response['json']);
        $this->assertArrayHasKey('country', $response['json']);
        $this->assertArrayHasKey('continent', $response['json']);
        $this->assertArrayHasKey('continentCode', $response['json']);
        $this->assertNotEquals('--', $response['json']['countryCode']);
    }

    public function testGetIpRejectsInvalidIp(): void
    {
        $response = $this->getJson('/v1/ips/not-an-ip', [
            'Authorization' => 'Bearer ' . $this->secret,
        ]);

        $this->assertEquals(400, $response['status']);
    }

    public function testGetIpHandlesUnknownIp(): void
    {
        // RFC 5737 TEST-NET address - unlikely to be in geodb
        $response = $this->getJson('/v1/ips/192.0.2.1', [
            'Authorization' => 'Bearer ' . $this->secret,
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertEquals('192.0.2.1', $response['json']['ip']);
        $this->assertEquals('--', $response['json']['countryCode']);
        $this->assertEquals('', $response['json']['country']);
        $this->assertEquals('', $response['json']['continent']);
        $this->assertEquals('--', $response['json']['continentCode']);
    }

    public function testGetIpv6ReturnsGeoData(): void
    {
        // Google DNS IPv6
        $response = $this->getJson('/v1/ips/2001:4860:4860::8888', [
            'Authorization' => 'Bearer ' . $this->secret,
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertEquals('2001:4860:4860::8888', $response['json']['ip']);
        $this->assertArrayHasKey('countryCode', $response['json']);
    }
}

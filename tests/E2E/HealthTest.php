<?php

namespace Tests\E2E;

class HealthTest extends Base
{
    public function testHealthEndpointReturnsOk(): void
    {
        $response = $this->getJson('/v1/health');

        $this->assertEquals(200, $response['status']);
        $this->assertEquals('ok', $response['json']['status']);
    }

    public function testHealthEndpointDoesNotRequireAuth(): void
    {
        // No authorization header - should still return 200
        $response = $this->getJson('/v1/health');

        $this->assertEquals(200, $response['status']);
    }
}

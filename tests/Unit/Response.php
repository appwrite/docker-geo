<?php

namespace Tests\Unit;

use Utopia\Http\Response as HttpResponse;

/**
 * Response that sends nothing, so a request can run outside a server.
 */
class Response extends HttpResponse
{
    public function write(string $content): bool
    {
        return true;
    }

    public function end(?string $content = null): void
    {
    }

    protected function sendStatus(int $statusCode): void
    {
    }

    public function sendHeader(string $key, array $value): void
    {
    }

    protected function sendCookie(string $name, string $value, array $options): void
    {
    }
}

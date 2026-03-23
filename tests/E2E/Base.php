<?php

namespace Tests\E2E;

use PHPUnit\Framework\TestCase;

abstract class Base extends TestCase
{
    protected string $baseUrl;
    protected string $secret;

    protected function setUp(): void
    {
        $host = getenv('GEO_TEST_HOST') ?: 'localhost';
        $port = getenv('GEO_TEST_PORT') ?: '8080';
        $this->secret = getenv('GEO_TEST_SECRET') ?: 'test-secret';
        $this->baseUrl = "http://{$host}:{$port}";
    }

    /**
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    protected function request(string $method, string $path, array $headers = []): array
    {
        $url = $this->baseUrl . $path;

        $httpHeaders = [];
        foreach ($headers as $key => $value) {
            $httpHeaders[] = "{$key}: {$value}";
        }

        $context = \stream_context_create([
            'http' => [
                'method' => $method,
                'header' => \implode("\r\n", $httpHeaders),
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $body = \file_get_contents($url, false, $context);
        $statusCode = 0;
        $responseHeaders = [];

        if (isset($http_response_header)) {
            // First line is status, e.g. "HTTP/1.1 200 OK"
            if (\preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $matches)) {
                $statusCode = (int) $matches[1];
            }
            foreach ($http_response_header as $header) {
                if (\str_contains($header, ':')) {
                    [$key, $value] = \explode(':', $header, 2);
                    $responseHeaders[\strtolower(\trim($key))] = \trim($value);
                }
            }
        }

        return [
            'status' => $statusCode,
            'body' => $body !== false ? $body : '',
            'headers' => $responseHeaders,
        ];
    }

    protected function getJson(string $path, array $headers = []): array
    {
        $response = $this->request('GET', $path, $headers);
        $response['json'] = \json_decode($response['body'], true) ?? [];
        return $response;
    }
}

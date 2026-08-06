<?php

namespace Appwrite\Geo;

use Throwable;
use Utopia\Fetch\Client;

final readonly class GeoIp
{
    private Client $client;

    public function __construct(
        private string $endpoint,
        private string $secret,
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client();
        $this->client
            ->addHeader('Accept', Client::CONTENT_TYPE_APPLICATION_JSON)
            ->setTimeout(1000);
    }

    public function getCountryCode(string $ip): ?string
    {
        $geo = $this->get($ip);

        return $geo?->getCountryCode();
    }

    public function get(string $ip): ?GeoRecord
    {
        if ($this->endpoint === '' || $this->secret === '') {
            return null;
        }

        try {
            $response = $this->client
                ->addHeader('Authorization', 'Bearer ' . $this->secret)
                ->fetch(
                    \rtrim($this->endpoint, '/') . '/v1/ips/' . \rawurlencode($ip),
                    Client::METHOD_GET,
                );

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            $body = \json_decode((string) $response->getBody(), true);
        } catch (Throwable) {
            return null;
        }

        return \is_array($body) ? GeoRecord::fromArray($body) : null;
    }
}

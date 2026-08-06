<?php

namespace Appwrite\Geo;

final readonly class GeoRecord
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getCountryCode(): ?string
    {
        $countryCode = $this->data['countryCode'] ?? null;

        return \is_string($countryCode) && $countryCode !== ''
            ? $countryCode
            : null;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}

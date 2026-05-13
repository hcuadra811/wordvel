<?php

declare(strict_types=1);

namespace App\Data;

final class WordvelExampleRequest
{
    public function __construct(
        public readonly string $name,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) ($payload['name'] ?? 'WordVel'),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Data;

final class WordvelExampleResource
{
    public function __construct(
        public readonly string $message,
        public readonly string $container,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'container' => $this->container,
        ];
    }
}

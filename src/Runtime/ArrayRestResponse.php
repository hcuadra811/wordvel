<?php

declare(strict_types=1);

namespace Wordvel\Runtime;

final class ArrayRestResponse
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data,
        public readonly int $status = 200,
    ) {}
}

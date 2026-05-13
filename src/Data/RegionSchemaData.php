<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Spatie\LaravelData\Data;

final class RegionSchemaData extends Data
{
    /**
     * @param RegionBlockSchemaData[] $blocks
     */
    public function __construct(
        public string $key,
        public string $name,
        public int $order,
        public array $blocks,
    ) {}
}

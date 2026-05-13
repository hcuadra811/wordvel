<?php

declare(strict_types=1);

namespace Wordvel\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class ApiEndpoint
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public string $summary,
        public string $description = '',
        public array $tags = [],
    ) {}
}

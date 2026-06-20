<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class WordVelResource
{
    /**
     * @param class-string $model
     * @param array<int, string> $searchFields
     */
    public function __construct(
        public ?string $model = null,
        public ?string $key = null,
        public ?string $label = null,
        public ?string $pluralLabel = null,
        public bool $manualFields = false,
        public ?string $titleField = null,
        public ?string $orderBy = null,
        public string $orderDirection = 'asc',
        public ?string $menuLabel = null,
        public ?string $menuIcon = null,
        public ?int $menuPosition = null,
        public array $searchFields = [],
        public bool $showInMenu = true,
        public bool $showInOverview = true,
        public ?int $overviewOrder = null,
        public bool $routable = true,
        public ?string $slugPrefix = null,
        public ?string $slugField = 'slug',
        public bool $includeId = false,
        public bool $includeTimestamps = false,
    ) {}
}

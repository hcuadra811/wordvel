<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Support\Collection;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Description;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;

/** @typescript */
final class MenuResource extends BaseData
{
    public function __construct(
        #[Example('header')]
        public string $location,
        #[Example('Main Menu')]
        public ?string $name,
        #[Description('Ordered WordPress nav menu items assigned to this menu location.')]
        /** @var Collection<int, MenuItemResource> */
        public Collection $items,
    ) {}
}

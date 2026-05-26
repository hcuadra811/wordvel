<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

use Illuminate\Support\Collection;

final class WordPressMenu
{
    /**
     * @param Collection<int, WordPressMenuItem> $items
     */
    public function __construct(
        public readonly string $location,
        public readonly ?string $name,
        public readonly Collection $items,
    ) {}
}

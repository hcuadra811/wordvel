<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

final class WordPressMenuItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $parentId,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $target,
        public readonly int $order,
        public readonly string $type,
        public readonly string $object,
        public readonly int $objectId,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Data;

use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;

/** @typescript */
final class MenuItemResource extends BaseData
{
    public function __construct(
        #[Example(42)]
        public int $id,
        #[Example(0)]
        public int $parent_id,
        #[Example('Architecture')]
        public string $title,
        #[Example('#architecture')]
        public string $url,
        #[Example('_blank')]
        public ?string $target,
        #[Example(1)]
        public int $order,
        #[Example('custom')]
        public string $type,
        #[Example('custom')]
        public string $object,
        #[Example(0)]
        public int $object_id,
    ) {}
}

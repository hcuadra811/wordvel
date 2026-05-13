<?php

declare(strict_types=1);

namespace App\Data;

/** @typescript */
final class SiteMenusResource extends BaseData
{
    public function __construct(
        public MenuResource $header,
        public MenuResource $footer,
    ) {}
}

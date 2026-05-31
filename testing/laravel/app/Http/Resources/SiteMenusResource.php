<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Langsys\ApiKit\Data\BaseData;

/** @typescript */
class SiteMenusResource extends BaseData
{
    public function __construct(
        public MenuResource $header,
        public MenuResource $footer,
    ) {}
}

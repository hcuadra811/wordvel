<?php

declare(strict_types=1);

namespace Wordvel\Regions;

use Wordvel\Data\RegionSchemaData;

abstract class RegionSchema
{
    abstract public function toData(): RegionSchemaData;
}

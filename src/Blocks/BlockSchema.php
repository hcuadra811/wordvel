<?php

declare(strict_types=1);

namespace Wordvel\Blocks;

use Wordvel\Data\BlockSchemaData;

abstract class BlockSchema
{
    abstract public function toData(): BlockSchemaData;
}

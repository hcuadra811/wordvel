<?php

declare(strict_types=1);

namespace App\Wordvel\Regions;

use Wordvel\Data\RegionSchemaData;
use Wordvel\Regions\Blocks\Block;
use Wordvel\Regions\Region;
use Wordvel\Regions\RegionSchema;

final class FooterRegion extends RegionSchema
{
    public function toData(): RegionSchemaData
    {
        return Region::make('footer', 'Footer')
            ->order(2)
            ->blocks([
                Block::menu('footer-navigation', 'Footer Navigation', 'footer'),
                Block::text('footer-note', 'Footer Note', 'Powered by WordVel.'),
            ])
            ->toData();
    }
}

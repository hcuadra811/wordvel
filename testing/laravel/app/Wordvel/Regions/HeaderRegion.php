<?php

declare(strict_types=1);

namespace App\Wordvel\Regions;

use Wordvel\Data\RegionSchemaData;
use Wordvel\Regions\Blocks\Block;
use Wordvel\Regions\Region;
use Wordvel\Regions\RegionSchema;

final class HeaderRegion extends RegionSchema
{
    public function toData(): RegionSchemaData
    {
        return Region::make('header', 'Header')
            ->order(1)
            ->blocks([
                Block::logo('site-logo', 'Site Logo'),
                Block::menu('primary-navigation', 'Primary Navigation', 'header'),
                Block::link('cta', 'Call to Action'),
            ])
            ->toData();
    }
}

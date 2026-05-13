<?php

declare(strict_types=1);

namespace Wordvel\Regions;

use Wordvel\Data\RegionBlockSchemaData;
use Wordvel\Data\RegionSchemaData;
use Wordvel\Regions\Blocks\Block;

final class Region
{
    /**
     * @var Block[]
     */
    private array $blocks = [];

    private int $order = 50;

    private function __construct(
        private readonly string $key,
        private readonly string $name,
    ) {}

    public static function make(string $key, string $name): self
    {
        return new self($key, $name);
    }

    /**
     * @param Block[] $blocks
     */
    public function blocks(array $blocks): self
    {
        $this->blocks = $blocks;

        return $this;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function toData(): RegionSchemaData
    {
        return new RegionSchemaData(
            key: $this->key,
            name: $this->name,
            order: $this->order,
            blocks: array_map(
                static fn (Block $block): RegionBlockSchemaData => $block->toData(),
                $this->blocks,
            ),
        );
    }
}

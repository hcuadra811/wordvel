<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

final class WordPress
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly MediaRepository $media,
        private readonly OptionRepository $options,
        private readonly MenuRepository $menus,
    ) {}

    public function pages(): PageRepository
    {
        return $this->pages;
    }

    public function media(): MediaRepository
    {
        return $this->media;
    }

    public function options(): OptionRepository
    {
        return $this->options;
    }

    public function menus(): MenuRepository
    {
        return $this->menus;
    }
}

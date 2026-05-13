<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

final class WordPress
{
    public function pages(): PageRepository
    {
        return new PageRepository();
    }

    public function media(): MediaRepository
    {
        return new MediaRepository();
    }
}

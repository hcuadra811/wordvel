<?php

declare(strict_types=1);

namespace App\Data;

use Langsys\OpenApiDocsGenerator\Generators\Attributes\Description;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;

/** @typescript */
final class SiteResource extends BaseData
{
    public function __construct(
        #[Example('WordVel Testing')]
        public string $name,
        #[Example('')]
        public string $description,
        #[Example('http://wordvel-wp.test/')]
        public string $url,
        #[Description('Theme options defined by the Laravel API and editable through WordPress-backed storage.')]
        public ThemeOptionsResource $theme,
        #[Description('WordPress menus keyed by Laravel-requested menu location.')]
        public SiteMenusResource $menus,
    ) {}
}

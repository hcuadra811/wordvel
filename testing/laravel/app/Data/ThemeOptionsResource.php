<?php

declare(strict_types=1);

namespace App\Data;

use Wordvel\ThemeOptions\Attributes\AsThemeOptions;
use Wordvel\ThemeOptions\Attributes\OptionGroup;

/** @typescript */
#[AsThemeOptions('site', 'Theme Options')]
final class ThemeOptionsResource extends BaseData
{
    public function __construct(
        #[OptionGroup('Logo')]
        public ThemeLogoResource $logo,
    ) {}
}

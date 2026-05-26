<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\MenuItemResource;
use App\Data\MenuResource;
use App\Data\SiteMenusResource;
use App\Data\SiteResource;
use App\Data\ThemeLogoResource;
use App\Data\ThemeOptionsResource;
use Illuminate\Support\Collection;
use Wordvel\WordPress\WordPress;
use Wordvel\WordPress\WordPressMenu;
use Wordvel\WordPress\WordPressMenuItem;

final class WordvelSiteContent
{
    public function __construct(
        private readonly WordPress $wordpress,
    ) {}

    public function site(): SiteResource
    {
        return new SiteResource(
            name: (string) $this->wordpress->options()->value('blogname', 'WordVel'),
            description: (string) $this->wordpress->options()->value('blogdescription', ''),
            url: rtrim((string) $this->wordpress->options()->value('home', 'http://wordvel-wp.test'), '/') . '/',
            theme: $this->themeOptions(),
            menus: new SiteMenusResource(
                header: $this->menu('header'),
                footer: $this->menu('footer'),
            ),
        );
    }

    private function themeOptions(): ThemeOptionsResource
    {
        $options = $this->wordpress->options()->array('wordvel_theme_options');
        $logo = is_array($options['logo'] ?? null) ? $options['logo'] : [];

        return new ThemeOptionsResource(
            logo: new ThemeLogoResource(
                type: (string) ($logo['type'] ?? $this->themeOptionDefault('logo', 'type')),
                text: (string) ($logo['text'] ?? $this->themeOptionDefault('logo', 'text')),
                font_family: (string) ($logo['font_family'] ?? $this->themeOptionDefault('logo', 'font_family')),
            ),
        );
    }

    private function menu(string $location): MenuResource
    {
        $menu = $this->wordpress->menus()->findByLocation($location);

        return new MenuResource(
            location: $location,
            name: $menu->name,
            items: $this->menuItems($menu),
        );
    }

    /**
     * @return Collection<int, MenuItemResource>
     */
    private function menuItems(WordPressMenu $menu): Collection
    {
        return $menu->items->map(static fn (WordPressMenuItem $item): MenuItemResource => new MenuItemResource(
            id: $item->id,
            parent_id: $item->parentId,
            title: $item->title,
            url: $item->url,
            target: $item->target,
            order: $item->order,
            type: $item->type,
            object: $item->object,
            object_id: $item->objectId,
        ));
    }

    private function themeOptionDefault(string $group, string $field): mixed
    {
        $manifestPath = storage_path('wordvel/manifest.json');

        if (! is_file($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $groups = is_array($manifest) ? ($manifest['theme_options']['groups'] ?? []) : [];

        foreach ($groups as $optionGroup) {
            if (! is_array($optionGroup) || ($optionGroup['key'] ?? null) !== $group) {
                continue;
            }

            foreach (($optionGroup['fields'] ?? []) as $optionField) {
                if (is_array($optionField) && ($optionField['key'] ?? null) === $field) {
                    return $optionField['default'] ?? null;
                }
            }
        }

        return null;
    }
}

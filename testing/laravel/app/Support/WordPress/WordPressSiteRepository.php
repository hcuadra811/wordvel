<?php

declare(strict_types=1);

namespace App\Support\WordPress;

use App\Data\MenuItemResource;
use App\Data\MenuResource;
use App\Data\SiteMenusResource;
use App\Data\SiteResource;
use App\Data\ThemeLogoResource;
use App\Data\ThemeOptionsResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class WordPressSiteRepository
{
    public function site(): SiteResource
    {
        return new SiteResource(
            name: (string) $this->option('blogname', 'WordVel'),
            description: (string) $this->option('blogdescription', ''),
            url: rtrim((string) $this->option('home', 'http://wordvel-wp.test'), '/') . '/',
            theme: $this->themeOptions(),
            menus: new SiteMenusResource(
                header: $this->menu('header'),
                footer: $this->menu('footer'),
            ),
        );
    }

    private function themeOptions(): ThemeOptionsResource
    {
        $options = $this->arrayOption('wordvel_theme_options');
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
        $menuId = $this->menuIdForLocation($location);

        if ($menuId === null) {
            return new MenuResource($location, null, collect());
        }

        $menu = DB::connection('wordpress')
            ->table('terms')
            ->where('term_id', $menuId)
            ->first();

        return new MenuResource(
            location: $location,
            name: $menu !== null ? (string) $menu->name : null,
            items: $this->menuItems($menuId),
        );
    }

    /**
     * @return Collection<int, MenuItemResource>
     */
    private function menuItems(int $menuId): Collection
    {
        $items = DB::connection('wordpress')
            ->table('posts')
            ->join('term_relationships', 'posts.ID', '=', 'term_relationships.object_id')
            ->join('term_taxonomy', 'term_relationships.term_taxonomy_id', '=', 'term_taxonomy.term_taxonomy_id')
            ->where('term_taxonomy.taxonomy', 'nav_menu')
            ->where('term_taxonomy.term_id', $menuId)
            ->where('posts.post_type', 'nav_menu_item')
            ->where('posts.post_status', 'publish')
            ->orderBy('posts.menu_order')
            ->select(['posts.ID', 'posts.post_title', 'posts.menu_order'])
            ->get();

        $meta = DB::connection('wordpress')
            ->table('postmeta')
            ->whereIn('post_id', $items->pluck('ID')->all())
            ->whereIn('meta_key', [
                '_menu_item_menu_item_parent',
                '_menu_item_object_id',
                '_menu_item_object',
                '_menu_item_type',
                '_menu_item_url',
                '_menu_item_target',
            ])
            ->get()
            ->groupBy('post_id')
            ->map(static fn (Collection $rows): array => $rows->pluck('meta_value', 'meta_key')->all());

        return $items->map(static function (object $item) use ($meta): MenuItemResource {
            $itemMeta = $meta->get($item->ID, []);

            return new MenuItemResource(
                id: (int) $item->ID,
                parent_id: (int) ($itemMeta['_menu_item_menu_item_parent'] ?? 0),
                title: html_entity_decode((string) $item->post_title, ENT_QUOTES),
                url: (string) ($itemMeta['_menu_item_url'] ?? '#'),
                target: ($itemMeta['_menu_item_target'] ?? '') !== '' ? (string) $itemMeta['_menu_item_target'] : null,
                order: (int) $item->menu_order,
                type: (string) ($itemMeta['_menu_item_type'] ?? 'custom'),
                object: (string) ($itemMeta['_menu_item_object'] ?? 'custom'),
                object_id: (int) ($itemMeta['_menu_item_object_id'] ?? 0),
            );
        });
    }

    private function menuIdForLocation(string $location): ?int
    {
        $themeMods = $this->arrayOption('theme_mods_wordvel-headless');
        $locations = is_array($themeMods['nav_menu_locations'] ?? null) ? $themeMods['nav_menu_locations'] : [];
        $menuId = $locations[$location] ?? null;

        return is_numeric($menuId) ? (int) $menuId : null;
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return DB::connection('wordpress')
            ->table('options')
            ->where('option_name', $key)
            ->value('option_value') ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayOption(string $key): array
    {
        $value = $this->option($key);

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = @unserialize($value, ['allowed_classes' => false]);

        return is_array($decoded) ? $decoded : [];
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

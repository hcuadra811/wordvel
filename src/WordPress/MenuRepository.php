<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

use Illuminate\Support\Collection;

final class MenuRepository
{
    public function __construct(
        private readonly WordPressCodex $wordpress,
    ) {}

    public function findByLocation(string $location, string $theme = 'wordvel-headless'): WordPressMenu
    {
        $menu = $this->wordpress->call('menuByLocation', [
            'location' => $location,
            'theme' => $theme,
        ]);

        $menu = is_array($menu) ? $menu : [];

        return new WordPressMenu(
            location: $location,
            name: isset($menu['name']) ? (string) $menu['name'] : null,
            items: $this->items($menu['items'] ?? []),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return Collection<int, WordPressMenuItem>
     */
    private function items(array $items): Collection
    {
        return collect($items)
            ->map(static function (array $item): WordPressMenuItem {
            return new WordPressMenuItem(
                id: (int) $item['id'],
                parentId: (int) ($item['parent_id'] ?? 0),
                title: (string) $item['title'],
                url: (string) ($item['url'] ?? '#'),
                target: ($item['target'] ?? '') !== '' ? (string) $item['target'] : null,
                order: (int) ($item['order'] ?? 0),
                type: (string) ($item['type'] ?? 'custom'),
                object: (string) ($item['object'] ?? 'custom'),
                objectId: (int) ($item['object_id'] ?? 0),
            );
        });
    }
}

<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

use Illuminate\Support\Facades\DB;

final class PageRepository
{
    public function findBySlug(string $slug): ?WordPressPage
    {
        $post = DB::connection((string) config('wordvel.connection', 'wordpress'))
            ->table('posts')
            ->where('post_type', 'page')
            ->where('post_name', $slug)
            ->whereIn('post_status', ['publish', 'draft'])
            ->first();

        if ($post === null) {
            return null;
        }

        return WordPressPage::fromDatabaseRecord($post);
    }
}

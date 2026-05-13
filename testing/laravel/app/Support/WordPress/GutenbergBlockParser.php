<?php

declare(strict_types=1);

namespace App\Support\WordPress;

use App\Data\MediaResource;
use App\Data\PageBlockResource;
use Spatie\LaravelData\Data;
use Wordvel\WordPress\WordPress;

final class GutenbergBlockParser
{
    public function __construct(
        private readonly WordPress $wordpress,
    ) {}

    /**
     * @return PageBlockResource[]
     */
    public function parse(string $content, array $blockSchemas): array
    {
        $matches = $this->selfClosingBlocks($content);
        preg_match_all('/<!--\s+wp:([a-z0-9-]+\/[a-z0-9-]+)(?:\s+(\{.*?\}))?\s*-->(.*?)<!--\s+\/wp:\1\s+-->/is', $content, $paired, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($paired as $match) {
            $matches[] = [
                'offset' => $match[0][1],
                'name' => $match[1][0],
                'attrs' => $match[2][0] ?? '',
                'html' => $match[3][0] ?? '',
            ];
        }

        usort($matches, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        if ($matches === []) {
            $html = trim($content);

            return $html === '' ? [] : [
                new PageBlockResource('html-0', 'html', 'Html', ['html' => $html]),
            ];
        }

        $blocks = [];

        foreach ($matches as $index => $match) {
            $name = $match['name'];
            $attrs = $match['attrs'] !== '' ? json_decode($match['attrs'], true) : [];
            $attrs = is_array($attrs) ? $attrs : [];

            if (str_starts_with($name, 'wordvel/')) {
                $key = substr($name, strlen('wordvel/'));
                $schema = $blockSchemas[$key] ?? null;
                $data = $this->data($schema, $attrs);

                $blocks[] = new PageBlockResource(
                    id: $key,
                    type: $key,
                    component: $schema['component'] ?? $key,
                    data: $data,
                );

                continue;
            }

            $blocks[] = new PageBlockResource(
                id: str_replace('/', '-', $name) . '-' . $index,
                type: 'wordpress_block',
                component: 'WordPressBlock',
                data: [
                    'name' => $name,
                    'attrs' => $attrs,
                    'html' => trim($match['html']),
                ],
            );
        }

        return $blocks;
    }

    /**
     * @return array<int, array{offset: int, name: string, attrs: string, html: string}>
     */
    private function selfClosingBlocks(string $content): array
    {
        $blocks = [];
        $offset = 0;

        while (($start = strpos($content, '<!-- wp:', $offset)) !== false) {
            $end = strpos($content, '-->', $start);

            if ($end === false) {
                break;
            }

            $inside = trim(substr($content, $start + strlen('<!-- wp:'), $end - $start - strlen('<!-- wp:')));
            $offset = $end + strlen('-->');

            if (! str_ends_with($inside, '/')) {
                continue;
            }

            $inside = trim(substr($inside, 0, -1));
            $space = strpos($inside, ' ');

            $blocks[] = [
                'offset' => $start,
                'name' => $space === false ? $inside : substr($inside, 0, $space),
                'attrs' => $space === false ? '' : trim(substr($inside, $space + 1)),
                'html' => '',
            ];
        }

        return $blocks;
    }

    private function data(?array $schema, array $attrs): array
    {
        if ($schema === null) {
            return $attrs;
        }

        $data = [];

        foreach (($schema['fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $data[$key] = $this->fieldValue($field, $attrs[$key] ?? ($field['default'] ?? null));
        }

        $class = $schema['data_class'] ?? null;

        if (is_string($class) && is_subclass_of($class, Data::class)) {
            return $class::from($data)->toArray();
        }

        return $data;
    }

    private function fieldValue(array $field, mixed $value): mixed
    {
        return match ((string) ($field['type'] ?? 'text')) {
            'image', 'media' => $this->media($value),
            'repeater' => is_array($value) ? array_values($value) : [],
            'number' => is_numeric($value) ? $value + 0 : null,
            'boolean' => $value === true || $value === '1' || $value === 1,
            default => $value,
        };
    }

    private function media(mixed $value): ?MediaResource
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        $media = $this->wordpress->media()->find((int) $value);

        if ($media === null) {
            return null;
        }

        return new MediaResource(
            id: $media->id,
            url: $media->url,
            mime_type: $media->mimeType,
            title: $media->title,
            alt: $media->alt,
            width: $media->width,
            height: $media->height,
        );
    }
}

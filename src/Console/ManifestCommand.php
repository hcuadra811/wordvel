<?php

declare(strict_types=1);

namespace Wordvel\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Wordvel\Blocks\Attributes\AsBlock;
use Wordvel\Blocks\Attributes\Field;
use Wordvel\Data\BlockSchemaData;
use Wordvel\Data\FieldSchemaData;
use Wordvel\Data\ThemeOptionGroupSchemaData;
use Wordvel\Data\ThemeOptionsSchemaData;
use Wordvel\Regions\RegionSchema;
use Wordvel\ThemeOptions\Attributes\AsThemeOptions;
use Wordvel\ThemeOptions\Attributes\OptionGroup;

final class ManifestCommand extends Command
{
    protected $signature = 'wordvel:manifest';

    protected $description = 'Write the WordVel route manifest from Laravel route metadata.';

    public function handle(Router $router): int
    {
        $manifest = [
            'namespace' => 'wordvel',
            'prefix' => 'v1',
            'editor' => [
                'styles' => config('wordvel.editor.styles', []),
                'preview_url' => url('/api/wordvel/editor-preview'),
                'css_url' => url('/api/wordvel/editor.css'),
            ],
            'routes' => [],
            'regions' => [],
            'theme_options' => null,
            'blocks' => [],
        ];

        foreach ($router->getRoutes() as $route) {
            if (! $this->isWordvelRoute($route)) {
                continue;
            }

            $action = $route->getAction();
            $defaults = $route->defaults;
            $uses = $action['uses'] ?? null;

            if (! is_string($uses) || ! str_contains($uses, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $uses, 2);
            $endpoint = $this->endpoint($controller, $method);

            $manifest['routes'][] = [
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'path' => $this->wordpressPath($route),
                'laravel_uri' => '/' . ltrim($route->uri(), '/'),
                'name' => $route->getName(),
                'controller' => $controller,
                'method' => $method,
                'documented' => $endpoint !== null,
                'docs' => $endpoint,
                'request_dto' => $defaults['request_dto'] ?? null,
                'response_dto' => $defaults['response_dto'] ?? null,
                'capability' => $defaults['wp_capability'] ?? null,
            ];
        }

        $manifest['regions'] = $this->regions();
        $manifest['theme_options'] = $this->themeOptions();
        $manifest['blocks'] = $this->blocks();

        $path = base_path('storage/wordvel/manifest.json');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents(
            $path,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        );

        $this->info('Wrote WordVel manifest: ' . $path);
        $this->line('Routes: ' . count($manifest['routes']));

        return self::SUCCESS;
    }

    private function isWordvelRoute(Route $route): bool
    {
        $defaults = $route->defaults;

        return isset($defaults['request_dto']) || isset($defaults['response_dto']) || isset($defaults['wp_capability']);
    }

    private function wordpressPath(Route $route): string
    {
        $uri = trim($route->uri(), '/');

        foreach (['api/v1/', 'api/', 'v1/'] as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                $uri = substr($uri, strlen($prefix));
                break;
            }
        }

        return '/' . trim($uri, '/');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function regions(): array
    {
        $path = app_path('Wordvel/Regions');

        if (! is_dir($path)) {
            return [];
        }

        $regions = [];

        foreach (glob($path . '/*Region.php') ?: [] as $file) {
            $class = 'App\\Wordvel\\Regions\\' . basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, RegionSchema::class)) {
                continue;
            }

            /** @var RegionSchema $region */
            $region = app($class);
            $regions[] = $region->toData()->toArray();
        }

        usort($regions, static fn (array $a, array $b): int => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));

        return $regions;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function themeOptions(): ?array
    {
        $path = app_path('Data');

        if (! is_dir($path)) {
            return null;
        }

        foreach (glob($path . '/*ThemeOptionsResource.php') ?: [] as $file) {
            $class = 'App\\Data\\' . basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, Data::class)) {
                continue;
            }

            $schema = $this->themeOptionsSchema($class);

            if ($schema !== null) {
                return $schema->toArray();
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    private function themeOptionsSchema(string $class): ?ThemeOptionsSchemaData
    {
        $reflection = new ReflectionClass($class);
        $attribute = $reflection->getAttributes(AsThemeOptions::class)[0] ?? null;

        if ($attribute === null) {
            return null;
        }

        /** @var AsThemeOptions $themeOptions */
        $themeOptions = $attribute->newInstance();
        $constructor = $reflection->getConstructor();

        return new ThemeOptionsSchemaData(
            key: $themeOptions->key,
            name: $themeOptions->name,
            groups: $constructor === null
                ? []
                : array_values(array_filter(array_map(
                    fn (ReflectionParameter $parameter): ?ThemeOptionGroupSchemaData => $this->themeOptionGroupSchema($parameter, $reflection),
                    $constructor->getParameters(),
                ))),
        );
    }

    private function themeOptionGroupSchema(ReflectionParameter $parameter, ReflectionClass $declaringClass): ?ThemeOptionGroupSchemaData
    {
        $type = $parameter->getType();

        if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $class = $type->getName();

        if (! is_subclass_of($class, Data::class)) {
            return null;
        }

        $attribute = $parameter->getAttributes(OptionGroup::class)[0] ?? null;
        $group = $attribute?->newInstance();

        return new ThemeOptionGroupSchemaData(
            key: $parameter->getName(),
            label: $group?->label ?? str($parameter->getName())->replace('_', ' ')->title()->toString(),
            fields: $this->dataFields($class),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blocks(): array
    {
        $path = app_path('Data/Blocks');

        if (! is_dir($path)) {
            return [];
        }

        $blocks = [];

        foreach (glob($path . '/*BlockData.php') ?: [] as $file) {
            $class = 'App\\Data\\Blocks\\' . basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $schema = $this->blockSchema($class);

            if ($schema !== null) {
                $blocks[] = $schema->toArray();
            }
        }

        return $blocks;
    }

    /**
     * @param class-string $class
     */
    private function blockSchema(string $class): ?BlockSchemaData
    {
        $reflection = new ReflectionClass($class);
        $attribute = $reflection->getAttributes(AsBlock::class)[0] ?? null;

        if ($attribute === null) {
            return null;
        }

        /** @var AsBlock $block */
        $block = $attribute->newInstance();
        $constructor = $reflection->getConstructor();

        return new BlockSchemaData(
            key: $block->key,
            name: $block->name,
            component: $block->component,
            data_class: $class,
            fields: $constructor === null
                ? []
                : array_values(array_filter(array_map(
                    fn (ReflectionParameter $parameter): ?FieldSchemaData => $this->fieldSchema($parameter, $reflection),
                    $constructor->getParameters(),
                ))),
        );
    }

    private function fieldSchema(ReflectionParameter $parameter, ReflectionClass $declaringClass): ?FieldSchemaData
    {
        $attribute = $parameter->getAttributes(Field::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

        if ($attribute === null) {
            return null;
        }

        /** @var Field $field */
        $field = $attribute->newInstance();
        $type = $parameter->getType();
        $required = $field->required || ! $parameter->allowsNull();
        $default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        $itemClass = $field->type === 'repeater' ? $this->repeaterItemClass($parameter, $declaringClass) : null;
        $itemFields = $itemClass === null ? [] : $this->dataFields($itemClass);

        return new FieldSchemaData(
            key: $parameter->getName(),
            type: $field->type,
            label: $field->label ?? str($parameter->getName())->replace('_', ' ')->title()->toString(),
            default: $default,
            required: $required,
            options: $field->options,
            item_type: $itemClass,
            fields: $itemFields,
        );
    }

    /**
     * @return FieldSchemaData[]
     */
    private function dataFields(string $class): array
    {
        if (! is_subclass_of($class, Data::class)) {
            return [];
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (ReflectionParameter $parameter): ?FieldSchemaData => $this->fieldSchema($parameter, $reflection),
            $constructor->getParameters(),
        )));
    }

    private function repeaterItemClass(ReflectionParameter $parameter, ReflectionClass $declaringClass): ?string
    {
        if (! $declaringClass->hasProperty($parameter->getName())) {
            return null;
        }

        $property = $declaringClass->getProperty($parameter->getName());
        $attribute = $property->getAttributes(DataCollectionOf::class)[0] ?? null;

        if ($attribute === null) {
            return null;
        }

        /** @var DataCollectionOf $collection */
        $collection = $attribute->newInstance();

        return $collection->class;
    }

    private function endpoint(string $controller, string $method): ?array
    {
        if (! class_exists($controller) || ! method_exists($controller, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($controller, $method);
        $docBlock = $reflection->getDocComment() ?: '';

        if (! str_contains($docBlock, '@OA\\')) {
            return null;
        }

        preg_match('/summary="([^"]+)"/', $docBlock, $summary);
        preg_match('/description="([^"]+)"/', $docBlock, $description);
        preg_match('/tags=\{([^}]+)\}/', $docBlock, $tags);

        return [
            'summary' => $summary[1] ?? null,
            'description' => $description[1] ?? null,
            'tags' => isset($tags[1])
                ? array_values(array_filter(array_map(
                    static fn (string $tag): string => trim($tag, " \t\n\r\0\x0B\"'"),
                    explode(',', $tags[1]),
                )))
                : [],
        ];
    }
}

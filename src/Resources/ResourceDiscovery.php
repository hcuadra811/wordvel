<?php

declare(strict_types=1);

namespace Wordvel\Resources;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Spatie\LaravelData\Data;
use Wordvel\Resources\Attributes\WordVelResource;

final class ResourceDiscovery
{
    /**
     * @return array<class-string<Data>>
     */
    public function classes(): array
    {
        $configured = config('wordvel.resources.classes', []);
        $classes = is_array($configured) ? array_values(array_filter($configured, 'is_string')) : [];

        foreach ($this->paths() as $path) {
            foreach ($this->classesInPath($path) as $class) {
                $classes[] = $class;
            }
        }

        $classes = array_values(array_unique($classes));

        return array_values(array_filter($classes, static function (string $class): bool {
            return class_exists($class)
                && is_subclass_of($class, Data::class)
                && (new ReflectionClass($class))->getAttributes(WordVelResource::class) !== [];
        }));
    }

    /**
     * @return array<int, string>
     */
    private function paths(): array
    {
        $paths = config('wordvel.resources.paths', [
            app_path('Http/Resources'),
            app_path('Data/Resources'),
        ]);

        return array_values(array_filter(
            is_array($paths) ? $paths : [],
            static fn (mixed $path): bool => is_string($path) && is_dir($path),
        ));
    }

    /**
     * @return array<class-string<Data>>
     */
    private function classesInPath(string $path): array
    {
        $classes = [];
        $root = realpath(app_path());

        if ($root === false) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();

            if (! is_string($realPath) || ! str_starts_with($realPath, $root . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $relative = substr($realPath, strlen($root) + 1);
            $class = 'App\\' . str_replace(['/', '.php'], ['\\', ''], $relative);

            if (class_exists($class) && is_subclass_of($class, Data::class)) {
                /** @var class-string<Data> $class */
                $classes[] = $class;
            }
        }

        return $classes;
    }
}

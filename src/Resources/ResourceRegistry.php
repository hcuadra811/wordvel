<?php

declare(strict_types=1);

namespace Wordvel\Resources;

use Wordvel\Data\ResourceSchemaData;

final class ResourceRegistry
{
    public function __construct(
        private readonly ResourceDiscovery $discovery,
        private readonly ResourceSchemaFactory $schemas,
    ) {}

    /**
     * @return ResourceSchemaData[]
     */
    public function all(): array
    {
        return array_values(array_filter(array_map(
            fn (string $class): ?ResourceSchemaData => $this->schemas->forClass($class),
            $this->discovery->classes(),
        )));
    }

    public function find(string $key): ?ResourceSchemaData
    {
        foreach ($this->all() as $schema) {
            if ($schema->key === $key) {
                return $schema;
            }
        }

        return null;
    }
}

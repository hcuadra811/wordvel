<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

abstract class BaseData extends Data
{
    public function filled(array $additionalData = [], bool $appendNullsFromRequest = false): array
    {
        $fields = array_filter(
            $this->all(),
            fn (mixed $value, string $key): bool => $value !== null && property_exists($this, $key),
            ARRAY_FILTER_USE_BOTH
        );

        $originalRequestFields = request()?->json()?->all();

        if ($appendNullsFromRequest && is_array($originalRequestFields)) {
            foreach ($originalRequestFields as $key => $data) {
                if (! property_exists($this, $key) || isset($fields[$key])) {
                    continue;
                }

                $fields[$key] = null;
            }
        }

        return [...$fields, ...$additionalData];
    }
}

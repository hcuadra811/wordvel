<?php

declare(strict_types=1);

namespace Wordvel\Runtime;

final class ResponseAdapter
{
    public function success(mixed $result): object
    {
        return $this->wpResponse([
            'status' => true,
            'data' => $this->serialize($result),
        ], 200);
    }

    public function error(\Throwable $exception): object
    {
        return $this->wpResponse([
            'status' => false,
            'error' => [
                'message' => $exception->getMessage(),
                'type' => $exception::class,
            ],
        ], 500);
    }

    private function wpResponse(array $payload, int $status): object
    {
        if (class_exists('WP_REST_Response')) {
            return new \WP_REST_Response($payload, $status);
        }

        return new ArrayRestResponse($payload, $status);
    }

    private function serialize(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return $value;
    }
}

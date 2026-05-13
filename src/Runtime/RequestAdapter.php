<?php

declare(strict_types=1);

namespace Wordvel\Runtime;

final class RequestAdapter
{
    /**
     * @return array<string, mixed>
     */
    public function payload(object $request): array
    {
        $json = $this->callIfExists($request, 'get_json_params');
        $body = $this->callIfExists($request, 'get_body_params');
        $query = $this->callIfExists($request, 'get_query_params');
        $route = $this->callIfExists($request, 'get_url_params');

        return array_merge(
            is_array($query) ? $query : [],
            is_array($body) ? $body : [],
            is_array($json) ? $json : [],
            is_array($route) ? $route : [],
        );
    }

    private function callIfExists(object $object, string $method): mixed
    {
        if (! method_exists($object, $method)) {
            return [];
        }

        return $object->{$method}();
    }
}

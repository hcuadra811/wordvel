<?php

declare(strict_types=1);

namespace Wordvel\Testing;

final class FakeWpRestRequest
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $json
     * @param array<string, mixed> $route
     */
    public function __construct(
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $json = [],
        private readonly array $route = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get_query_params(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_body_params(): array
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_json_params(): array
    {
        return $this->json;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_url_params(): array
    {
        return $this->route;
    }
}

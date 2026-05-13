<?php

declare(strict_types=1);

namespace Wordvel\Runtime;

use Illuminate\Contracts\Container\Container;
use ReflectionClass;
use ReflectionNamedType;

final class Dispatcher
{
    public function __construct(
        private readonly RequestAdapter $requests,
        private readonly ResponseAdapter $responses,
        private readonly ?Container $container = null,
    ) {}

    /**
     * @param array{
     *     controller: class-string,
     *     method: string,
     *     request_dto?: class-string|null,
     *     response_dto?: class-string|null,
     *     wp_request: object
     * } $route
     */
    public function dispatch(array $route): object
    {
        try {
            $controller = $this->resolve($route['controller']);
            $arguments = $this->buildArguments($controller, $route['method'], $route);
            $result = $controller->{$route['method']}(...$arguments);

            return $this->responses->success($result);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    private function resolve(string $class): object
    {
        if ($this->container !== null) {
            return $this->container->make($class);
        }

        return new $class();
    }

    /**
     * @param array{request_dto?: class-string|null, wp_request: object} $route
     * @return array<int, mixed>
     */
    private function buildArguments(object $controller, string $method, array $route): array
    {
        $reflection = new ReflectionClass($controller);
        $parameters = $reflection->getMethod($method)->getParameters();

        if ($parameters === []) {
            return [];
        }

        $dtoClass = $route['request_dto'] ?? null;
        $payload = $this->requests->payload($route['wp_request']);

        return array_map(function ($parameter) use ($dtoClass, $payload) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                return $payload;
            }

            $class = $type->getName();

            if ($dtoClass !== null && is_a($class, $dtoClass, true)) {
                return $this->hydrate($class, $payload);
            }

            if (method_exists($class, 'fromArray')) {
                return $class::fromArray($payload);
            }

            return new $class();
        }, $parameters);
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $payload
     */
    private function hydrate(string $class, array $payload): object
    {
        if (method_exists($class, 'fromArray')) {
            return $class::fromArray($payload);
        }

        return new $class(...$payload);
    }
}

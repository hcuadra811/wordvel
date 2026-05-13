<?php

declare(strict_types=1);

namespace Wordvel\Runtime;

use Illuminate\Contracts\Container\Container;

final class Application
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {}

    public static function boot(?Container $container = null): self
    {
        return new self(
            new Dispatcher(
                new RequestAdapter(),
                new ResponseAdapter(),
                $container,
            ),
        );
    }

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
        return $this->dispatcher->dispatch($route);
    }
}

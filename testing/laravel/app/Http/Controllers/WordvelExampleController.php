<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\WordvelExampleRequest;
use App\Data\WordvelExampleResource;
use Illuminate\Contracts\Foundation\Application;

final class WordvelExampleController
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function show(WordvelExampleRequest $request): WordvelExampleResource
    {
        return new WordvelExampleResource(
            message: "Hello, {$request->name}. WordPress-shaped input reached a Laravel controller.",
            container: $this->app::class,
        );
    }
}

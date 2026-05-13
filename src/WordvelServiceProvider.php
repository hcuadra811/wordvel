<?php

declare(strict_types=1);

namespace Wordvel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Route;
use Wordvel\Console\ManifestCommand;
use Wordvel\Console\ProofCommand;
use Wordvel\Runtime\Application;
use Wordvel\WordPress\WordPress;

final class WordvelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/wordvel.php', 'wordvel');

        $this->app->singleton(Application::class, function ($app): Application {
            return Application::boot($app);
        });

        $this->app->singleton(WordPress::class, fn (): WordPress => new WordPress());
    }

    public function boot(): void
    {
        Route::macro('requestDto', function (string $dtoClass): Route {
            $this->defaults('request_dto', $dtoClass);

            return $this;
        });

        Route::macro('responseDto', function (string $dtoClass): Route {
            $this->defaults('response_dto', $dtoClass);

            return $this;
        });

        Route::macro('wpCapability', function (string $capability): Route {
            $this->defaults('wp_capability', $capability);

            return $this;
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ManifestCommand::class,
                ProofCommand::class,
            ]);
        }
    }
}

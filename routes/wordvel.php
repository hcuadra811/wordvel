<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Wordvel\Http\Controllers\ResourceController;
use Wordvel\Http\Middleware\VerifyBridgeKey;

Route::prefix('api/wordvel')
    ->middleware([...Arr::wrap(config('wordvel.resources.middleware', ['api'])), VerifyBridgeKey::class])
    ->group(function (): void {
        Route::get('/resources', [ResourceController::class, 'schemas'])->name('wordvel.resources.schemas');
        Route::get('/resources/{resource}/options', [ResourceController::class, 'options'])->name('wordvel.resources.options');
        Route::get('/resources/{resource}', [ResourceController::class, 'index'])->name('wordvel.resources.index');
        Route::post('/resources/{resource}', [ResourceController::class, 'store'])->name('wordvel.resources.store');
        Route::get('/resources/{resource}/{id}', [ResourceController::class, 'show'])->name('wordvel.resources.show');
        Route::post('/resources/{resource}/{id}/relationships/{field}', [ResourceController::class, 'createRelated'])->name('wordvel.resources.relationships.store');
        Route::post('/resources/{resource}/{id}/relationships/{field}/media', [ResourceController::class, 'createRelatedFromMedia'])->name('wordvel.resources.relationships.media');
        Route::match(['put', 'patch'], '/resources/{resource}/{id}', [ResourceController::class, 'update'])->name('wordvel.resources.update');
        Route::delete('/resources/{resource}/{id}', [ResourceController::class, 'destroy'])->name('wordvel.resources.destroy');
    });

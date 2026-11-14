<?php

use App\Http\Requests\WordvelExampleRequest;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\WordvelEditorPreviewController;
use App\Http\Controllers\WordvelExampleController;
use Illuminate\Support\Facades\Route;
use App\Http\Requests\EditorPreviewRequest;

Route::prefix('wordvel')->group(function (): void {
    // The frontend preview sync sends X-Wordvel-Bridge-Key (WORDVEL_BRIDGE_KEY).
    Route::post('/editor-preview', [WordvelEditorPreviewController::class, 'store'])
        ->middleware('wordvel.bridge')
        ->name('wordvel.editor-preview.store')
        ->requestDto(EditorPreviewRequest::class)
        ->wpCapability('manage_options');

    Route::get('/editor-preview', [WordvelEditorPreviewController::class, 'show'])
        ->name('wordvel.editor-preview.show')
        ->wpCapability('manage_options');

    Route::get('/editor.css', [WordvelEditorPreviewController::class, 'css'])
        ->name('wordvel.editor-preview.css')
        ->wpCapability('manage_options');
});

Route::prefix('v1')->group(function (): void {
    Route::get('/site', [SiteController::class, 'show'])
        ->name('site.show');

    Route::get('/pages/{slug}', [PageController::class, 'show'])
        ->name('pages.show');

    Route::get('/example', [WordvelExampleController::class, 'show'])
        ->name('wordvel.example')
        ->requestDto(WordvelExampleRequest::class);
});

<?php

use App\Http\Requests\WordvelExampleRequest;
use App\Http\Resources\WordvelExampleResource;
use App\Http\Resources\PageResource;
use App\Http\Resources\SiteResource;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\WordvelEditorPreviewController;
use App\Http\Controllers\WordvelExampleController;
use Illuminate\Support\Facades\Route;
use App\Http\Requests\EditorPreviewRequest;
use App\Http\Resources\EditorPreviewResource;

Route::prefix('wordvel')->group(function (): void {
    Route::post('/editor-preview', [WordvelEditorPreviewController::class, 'store'])
        ->name('wordvel.editor-preview.store')
        ->requestDto(EditorPreviewRequest::class)
        ->responseDto(EditorPreviewResource::class)
        ->wpCapability('manage_options');

    Route::get('/editor-preview', [WordvelEditorPreviewController::class, 'show'])
        ->name('wordvel.editor-preview.show')
        ->responseDto(EditorPreviewResource::class)
        ->wpCapability('manage_options');

    Route::get('/editor.css', [WordvelEditorPreviewController::class, 'css'])
        ->name('wordvel.editor-preview.css')
        ->wpCapability('manage_options');
});

Route::prefix('v1')->group(function (): void {
    Route::get('/site', [SiteController::class, 'show'])
        ->name('site.show')
        ->responseDto(SiteResource::class);

    Route::get('/pages/{slug}', [PageController::class, 'show'])
        ->name('pages.show')
        ->responseDto(PageResource::class);

    Route::get('/example', [WordvelExampleController::class, 'show'])
        ->name('wordvel.example')
        ->requestDto(WordvelExampleRequest::class)
        ->responseDto(WordvelExampleResource::class);
});

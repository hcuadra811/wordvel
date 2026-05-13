<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/documentation', function () {
    return view('redoc');
})->name('api.documentation');

Route::get('/api/docs/{jsonFile}', function (string $jsonFile) {
    $path = storage_path("api-docs/$jsonFile");

    abort_unless(file_exists($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'no-cache',
    ]);
})->where('jsonFile', '[\w\-]+\.json')->name('api.docs');

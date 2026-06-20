<?php

return [
    'connection' => env('WORDVEL_DB_CONNECTION', 'wordpress'),
    'wordpress' => [
        'version' => env('WORDVEL_WORDPRESS_VERSION', '7.0'),
        'path' => env('WORDVEL_WORDPRESS_PATH', 'wordpress'),
    ],
    'editor' => [
        'styles' => array_values(array_filter([
            env('WORDVEL_EDITOR_STYLE_URL'),
        ])),
    ],
    'bridge' => [
        'expose_api_routes' => env('WORDVEL_BRIDGE_EXPOSE_API_ROUTES', true),
        'api_base_url' => env('WORDVEL_API_URL', env('APP_URL')),
        'include_prefixes' => ['api'],
        'exclude_prefixes' => [
            'api/internal',
            'api/_debug',
        ],
    ],
    'resources' => [
        'paths' => [
            app_path('Http/Resources'),
            app_path('Data/Resources'),
        ],
        'classes' => [],
        'middleware' => ['api'],
    ],
];

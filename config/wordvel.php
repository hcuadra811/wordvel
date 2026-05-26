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
];

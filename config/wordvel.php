<?php

return [
    'connection' => env('WORDVEL_DB_CONNECTION', 'wordpress'),
    'editor' => [
        'styles' => array_values(array_filter([
            env('WORDVEL_EDITOR_STYLE_URL'),
        ])),
    ],
];

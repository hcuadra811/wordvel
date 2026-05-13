<?php

return [
    'default' => 'default',

    'documentations' => [
        'default' => [
            'paths' => [
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'annotations' => [app_path(), base_path('../../src')],
            ],
        ],
    ],

    'defaults' => [
        'dto' => [
            'faker_attribute_mapper' => [
                '_id' => 'id',
                '_url' => 'url',
                '_at' => 'date',
            ],
            'custom_functions' => [
                'id' => [\Langsys\OpenApiDocsGenerator\Functions\CustomFunctions::class, 'id'],
                'date' => [\Langsys\OpenApiDocsGenerator\Functions\CustomFunctions::class, 'date'],
            ],
            'pagination_fields' => [
                ['name' => 'status', 'description' => 'Response status', 'content' => true, 'type' => 'bool'],
                ['name' => 'page', 'description' => 'Current page number', 'content' => 1, 'type' => 'int'],
                ['name' => 'records_per_page', 'description' => 'Number of records per page', 'content' => 10, 'type' => 'int'],
                ['name' => 'page_count', 'description' => 'Number of pages', 'content' => 1, 'type' => 'int'],
                ['name' => 'total_records', 'description' => 'Total number of items', 'content' => 1, 'type' => 'int'],
            ],
        ],

        'paths' => [
            'docs' => storage_path('api-docs'),
            'base' => env('OPENAPI_BASE_PATH', null),
            'excludes' => [],
        ],

        'scan_options' => [
            'processors' => [],
            'exclude' => [],
            'open_api_spec_version' => env('OPENAPI_SPEC_VERSION', '3.0.0'),
        ],

        'security_definitions' => [
            'security_schemes' => [],
            'security' => [],
        ],

        'generate_yaml_copy' => env('OPENAPI_GENERATE_YAML', false),
        'constants' => [],
        'endpoint_parameters' => [
            'enabled' => true,
        ],
    ],
];

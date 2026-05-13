<?php

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'wordvel-wp.test';
$_SERVER['SERVER_NAME'] = 'wordvel-wp.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require_once __DIR__ . '/wp-load.php';

$page = get_page_by_path('home');

if (! $page instanceof WP_Post) {
    $id = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'Home',
        'post_name' => 'home',
        'post_content' => wordvel_proof_home_content(),
    ], true);

    if (is_wp_error($id)) {
        echo $id->get_error_message() . PHP_EOL;
        exit(1);
    }

    echo "Created Home page {$id}.\n";

    return;
}

echo "Home page already exists.\n";

wp_update_post([
    'ID' => $page->ID,
    'post_content' => wordvel_proof_home_content(),
]);

echo "Seeded Home page with WordVel website blocks.\n";

function wordvel_proof_home_content(): string
{
    return implode("\n\n", [
        wordvel_proof_block('hero', [
            'eyebrow' => 'Laravel API. WordPress editor.',
            'headline' => 'Build Laravel APIs using WordPress as a headless CMS.',
            'subheadline' => null,
            'body' => 'WordVel gives teams a DTO-first way to model content, register editor fields, compose Gutenberg blocks, and ship clean API responses without writing WordPress-shaped application code.',
            'primary_action_label' => 'Explore the architecture',
            'primary_action_href' => '#architecture',
            'secondary_action_label' => 'View API shape',
            'secondary_action_href' => '#api',
            'stat_1_value' => 'DTO',
            'stat_1_label' => 'contracts drive docs, editor UI, and responses',
            'stat_2_value' => 'WP',
            'stat_2_label' => 'stays focused on content editing and storage',
            'stat_3_value' => 'SPA',
            'stat_3_label' => 'renders the experience with clean data',
        ]),
        wordvel_proof_block('principles', [
            'eyebrow' => 'Architecture',
            'heading' => 'Laravel stays Laravel. WordPress stays useful.',
            'item_1_title' => 'Class-based content contracts',
            'item_1_body' => 'Pages, blocks, options, regions, and custom fields are described with PHP DTOs instead of scattered plugin config.',
            'item_2_title' => 'WordPress without the mess',
            'item_2_body' => 'WordVel wraps WordPress interactions in Laravel-style modules so application code never has to speak raw WordPress.',
            'item_3_title' => 'Docs from the same source',
            'item_3_body' => 'OpenAPI schemas are generated from Laravel Data DTOs, including dynamic oneOf content block shapes.',
        ]),
        wordvel_proof_block('feature-grid', [
            'eyebrow' => 'What WordVel gives you',
            'heading' => 'A clean bridge between content modeling and content editing.',
            'features' => [
                [
                    'icon' => 'PanelsTopLeft',
                    'title' => 'Page DTOs',
                    'body' => 'Base WordPress page resources expose canonical fields like id, slug, title, status, and ordered blocks.',
                ],
                [
                    'icon' => 'Blocks',
                    'title' => 'Gutenberg block DTOs',
                    'body' => 'Developers define available blocks in code, while editors compose and reorder them in WordPress.',
                ],
                [
                    'icon' => 'Braces',
                    'title' => 'Typed API payloads',
                    'body' => 'React receives predictable content objects, including documented possible block data shapes.',
                ],
                [
                    'icon' => 'DatabaseZap',
                    'title' => 'WordPress bridge',
                    'body' => 'WordVel becomes the translation layer between Laravel services and WordPress content storage.',
                ],
                [
                    'icon' => 'Route',
                    'title' => 'Laravel routing',
                    'body' => 'The public API remains a normal Laravel API with controllers, resources, responses, and docs.',
                ],
                [
                    'icon' => 'FileCode2',
                    'title' => 'Code-defined editing',
                    'body' => 'Anything structural lives in the codebase. WordPress gives users the forms and composition tools.',
                ],
            ],
        ]),
        wordvel_proof_block('workflow', [
            'eyebrow' => 'Workflow',
            'heading' => 'From DTO to editor to API response.',
            'step_1_title' => 'Define DTOs',
            'step_1_body' => 'Model pages, blocks, fields, menus, and regions as Laravel Data classes.',
            'step_2_title' => 'Register editor UI',
            'step_2_body' => 'WordVel turns those classes into WordPress admin fields and Gutenberg blocks.',
            'step_3_title' => 'Compose content',
            'step_3_body' => 'Editors build pages in WordPress using familiar content tools.',
            'step_4_title' => 'Serve Laravel API',
            'step_4_body' => 'React consumes clean DTO-backed JSON from Laravel, not WordPress markup.',
        ]),
        wordvel_proof_block('api-preview', [
            'label' => 'API payload',
            'heading' => 'React consumes content, not WordPress markup.',
            'body' => 'The website can render blocks however it wants, while WordVel keeps the editable values and API docs aligned with the DTOs.',
            'code' => 'status: true | data: PageResource | blocks: oneOf[HeroBlockData, PrinciplesBlockData, FeatureGridBlockData]',
        ]),
    ]);
}

/**
 * @param array<string, mixed> $attributes
 */
function wordvel_proof_block(string $name, array $attributes): string
{
    return '<!-- wp:wordvel/' . $name . ' ' . wp_json_encode($attributes) . ' /-->';
}

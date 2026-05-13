# WordVel

WordVel is an early proof of concept for building Laravel APIs while using
WordPress as a headless CMS and editor.

The core idea is DTO-first content modeling:

- Laravel owns the API routes, controllers, resources, OpenAPI docs, and
  contracts.
- WordPress owns content editing and content storage.
- WordVel bridges the two without asking application code to speak raw
  WordPress patterns.

This repository is intentionally still exploratory. The current code proves the
shape of the framework before the install, compile, and packaging commands are
formalized.

## What Works

- Laravel route manifest generation from `routes/api.php`.
- WordPress editor blocks generated from Laravel Data DTOs.
- Page responses built by Laravel from WordPress page content.
- A `/api/v1/site` endpoint for site chrome, theme options, and WordPress menus.
- Theme option fields generated from DTO attributes.
- OpenAPI docs generated from Laravel Data DTOs and route annotations.
- React-generated Gutenberg previews synced through the WordVel editor preview
  endpoint.

## Repository Layout

```txt
src/                         WordVel package code
testing/laravel/             Laravel API proof app
testing/wordpress/           Local WordPress proof harness
testing/wordpress/wp-content/plugins/wordvel-proof/
                             Temporary WordPress bridge plugin
testing/wordpress/wp-content/themes/wordvel-headless/
                             Minimal headless WordPress theme
docs/                        Notes for deferred editor preview features
```

Downloaded WordPress core, Composer vendors, local environment files, and local
database files are ignored.

## DTO-First Theme Options

Theme options are defined as API DTOs:

```php
#[AsThemeOptions('site', 'Theme Options')]
final class ThemeOptionsResource extends BaseData
{
    public function __construct(
        #[OptionGroup('Logo')]
        public ThemeLogoResource $logo,
    ) {}
}
```

The nested DTO owns the editable fields, defaults, docs, and response shape:

```php
final class ThemeLogoResource extends BaseData
{
    public function __construct(
        #[Select(['text' => 'Text'], 'Logo Type', required: true)]
        public string $type = 'text',

        #[Text('Logo Text', required: true)]
        public string $text = 'wordvel',

        #[Text('Font Family', required: true)]
        public string $font_family = 'Righteous',
    ) {}
}
```

WordPress renders those fields from the generated manifest and saves values to
WordPress storage. Laravel returns the same shape from `/api/v1/site`.

## Local Proof Commands

From the Laravel proof app:

```bash
cd testing/laravel
php artisan wordvel:manifest
php artisan openapi:generate
```

Useful local endpoints:

```txt
GET http://wordvel-api.test/api/v1/site
GET http://wordvel-api.test/api/v1/pages/home
GET http://wordvel-api.test/api/documentation
GET http://wordvel-wp.test/wp-admin/admin.php?page=wordvel-theme-options
```

## Current Direction

The next slices are:

- Move more WordPress bridge behavior from proof code into package APIs.
- Keep editor forms generated from DTO contracts.
- Add a proper install command for the embedded WordPress setup.
- Continue improving React preview sync and inline Gutenberg editing.

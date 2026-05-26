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
- Framework-rendered Gutenberg previews synced through the WordVel editor
  preview endpoint, with the proof site currently using `@wordvel/react`.

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

## Installing Into A Laravel API

In a Laravel API app, require WordVel and run the installer:

```bash
composer require wordvel/wordvel
php artisan wordvel:install
```

For the fuller API starter shape, install the optional API kit first and let the
installer publish its conventions too:

```bash
composer require wordvel/wordvel langsys/laravel-api-development-kit
php artisan wordvel:install --with-api-kit
```

The installer publishes `config/wordvel.php`, enables `routes/api.php` in modern
Laravel apps when needed, scaffolds a DTO-backed `GET /api/v1/health` endpoint,
installs a local WordPress backend in `wordpress/`, adds a ReDocly API reference
at `/api/documentation`, generates `storage/wordvel/manifest.json`, and runs
`openapi:generate` when `langsys/openapi-docs-generator` is installed.
WordPress installs use the latest WordVel-supported version by default,
currently WordPress 7.0. Use `--skip-redocly` or `--skip-wordpress` when a
project needs to opt out of either scaffold.

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
- Continue improving adapter preview sync and inline Gutenberg editing.

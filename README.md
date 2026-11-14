# WordVel

WordVel lets a Laravel API own application behavior while WordPress owns editing, content storage, media, menus, and the admin experience.

The philosophy is codebase-as-configuration:

- Laravel routes, controllers, Data DTOs, models, enums, and OpenAPI annotations are the source of truth.
- WordPress reads a generated manifest and exposes the Laravel API through its REST surface.
- Editor screens are generated from code contracts by convention first, with attributes used only for unavoidable overrides.
- WordPress should not bypass Laravel API behavior. API bridge routes proxy over HTTP so middleware, routing, validation, route binding, response handling, and application code run normally.

## What WordVel Provides

- `wordvel:install` to publish config, starter routes, starter DTOs/controllers, local WordPress, ReDocly docs, and the WordPress bridge.
- `wordvel:manifest` to generate `storage/wordvel/manifest.json` from routes, blocks, theme options, regions, and resources.
- WordPress bridge plugin assets for Gutenberg blocks, editor previews, admin region pages, resource CRUD screens, and HTTP API route proxying.
- DTO-driven Gutenberg block schemas using `#[AsBlock]` and block field attributes.
- DTO-driven theme option schemas using `#[AsThemeOptions]` and `#[OptionGroup]`.
- DTO-driven resource editor schemas using `#[WordVelResource]`.
- Resource CRUD endpoints under `/api/wordvel/resources`, reachable only through the WordPress bridge.
- Editor preview sync endpoints under `/api/wordvel/editor-preview`.
- Optional API Kit and OpenAPI generator integration.

## Installation

```bash
composer require wordvel/wordvel
php artisan wordvel:install
```

With API Kit conventions:

```bash
composer require wordvel/wordvel langsys/laravel-api-development-kit
php artisan wordvel:install --with-api-kit
```

With OpenAPI docs:

```bash
composer require langsys/openapi-docs-generator
php artisan openapi:generate
```

Useful options:

```bash
php artisan wordvel:install --force
php artisan wordvel:install --skip-redocly
php artisan wordvel:install --skip-wordpress
php artisan wordvel:install --wordpress-dir=wordpress
```

After model/resource/route changes:

```bash
php artisan wordvel:manifest
php artisan openapi:generate
php artisan test
```

## Bridge Model

WordVel exposes Laravel API routes to WordPress by convention.

The generated manifest includes API routes whose URI matches `config('wordvel.bridge.include_prefixes')`, which defaults to `api`. WordPress registers matching REST routes under:

```txt
/wp-json/{manifest.namespace}/{manifest.prefix}
```

For example:

```txt
Laravel:   GET http://belle-api.test/api/v1/shop/products/{product}
WordPress: GET http://belle-wp.test/wp-json/wordvel/v1/shop/products/{product}
```

WordPress proxies the request to Laravel over HTTP. It preserves query params, route params, request body, and common headers such as `Accept-Language` and `Authorization`.

Bridge config:

```php
'bridge' => [
    'key' => env('WORDVEL_BRIDGE_KEY'),
    'expose_api_routes' => true,
    'api_base_url' => env('WORDVEL_API_URL', env('APP_URL')),
    'include_prefixes' => ['api'],
    'exclude_prefixes' => ['api/internal', 'api/_debug'],
],
```

Use route-level opt-out for rare internal endpoints:

```php
Route::get('/internal/report', Controller::class)
    ->withoutWordvelBridge();
```

Use route-level opt-in only when a route lives outside the included API prefixes:

```php
Route::get('/custom-public-feed', Controller::class)
    ->wordvelBridge();
```

`responseDto()` is kept as a backward-compatible route macro, but generated WordVel code no longer uses it. OpenAPI generation does not need it, and the WordPress bridge does not need it.

## Resource Editor API

The resource CRUD routes under `/api/wordvel/resources` only accept requests from the WordPress bridge. Browsers never call them directly:

```txt
Editor script -> wp.apiFetch('/wordvel-editor/v1/resources/...')  (WordPress login cookie + REST nonce)
WordPress     -> /api/wordvel/resources/...                        (X-Wordvel-Bridge-Key)
```

The editor scripts use `wp.apiFetch`, so the REST root works with any permalink setting and an expired nonce is refreshed and the request retried.

WordPress checks the user before forwarding:

- `GET {resource}/options` for a resource that a block relationship field references needs `edit_posts`, so block editor relationship pickers work for editors;
- every other resource request needs `manage_options`, the same capability as the resource admin screens.

WordPress then forwards the request to the manifest's `editor.resource_api_url` with an `X-Wordvel-Bridge-Key` header. It always asks Laravel for JSON and never relays a response body that is not JSON, so an HTML error page that echoes request headers never reaches the browser. Laravel's `Wordvel\Http\Middleware\VerifyBridgeKey` runs on every resource route, after `config('wordvel.resources.middleware')`, and answers `401` unless the header matches.

The key is `config('wordvel.bridge.key')` (`WORDVEL_BRIDGE_KEY`) when set, and otherwise an HMAC derived from `APP_KEY`. WordPress boots the same Laravel application, so both sides resolve the same key with no extra setup. With neither set, every request is refused. A published `config/wordvel.php` replaces the whole `bridge` array, so it needs the `'key' => env('WORDVEL_BRIDGE_KEY')` entry for `WORDVEL_BRIDGE_KEY` to take effect.

The resource API is forwarded server-side to `config('wordvel.bridge.api_base_url')`, like other bridge routes, so that URL must be reachable from the WordPress host.

Routes that require the bridge key are never exported as public WordPress REST routes in the manifest. Apps can require the key on their own server-to-server routes with the `wordvel.bridge` middleware alias. The caller has to send the key itself, which means setting `WORDVEL_BRIDGE_KEY` explicitly and sharing it with that caller.

## Editor Preview Sync

The frontend preview sync (`wordvel-react sync`) posts rendered block HTML and CSS to `POST /api/wordvel/editor-preview`, and WordPress renders that HTML inside the block editor. The route requires the bridge key, so only a caller holding it can change what the editor shows:

```php
Route::post('/editor-preview', [WordvelEditorPreviewController::class, 'store'])
    ->middleware('wordvel.bridge');
```

Set `WORDVEL_BRIDGE_KEY` explicitly in the API's `.env` (for example `openssl rand -hex 32`), and send the same value from the frontend's `wordvel.config.mjs`:

```js
export default {
  endpoint: 'http://example-api.test/api/wordvel/editor-preview',
  headers: { 'X-Wordvel-Bridge-Key': process.env.WORDVEL_BRIDGE_KEY },
  // ...
};
```

Reading the stored preview (`GET /api/wordvel/editor-preview`, `GET /api/wordvel/editor.css`) stays open.

## Resource CoC

A resource DTO is a Spatie Laravel Data class with `#[WordVelResource]`.

```php
#[WordVelResource(key: 'shop-products', menuLabel: 'Shop')]
final class ProductResource extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ProductStatus $status = ProductStatus::Draft,
        public ?string $description = null,
        public int $price_cents = 0,
        public int $created_at = 0,
    ) {}

    public static function fromModel(Product $product): self
    {
        return new self(...);
    }
}
```

WordVel infers:

- Model: from `fromModel(Product $product)`, then from `App\Models\Product`, then from a package namespace like `Wordvel\Shop\Models\Product`.
- Key: from class name, unless `key` is set.
- Label/plural label: from class name, unless set.
- `id`: omitted from WordPress editor by default.
- `*_at`: omitted from WordPress editor by default.
- Computed/unbacked constructor properties: omitted from WordPress editor by default.
- `string`: text field.
- `?string`, defaults, and `Optional`: not required.
- non-null `string` with no default: required.
- `slug`: slug field, generated from `name` when the model has a `name` column.
- names containing `description`, `notes`, `address`, or `reference`: rich text area.
- `int`/`float`: number field.
- `bool`: boolean field.
- backed enum: select field using enum values.
- localized fields: inferred from model casts/DB column type (`array`, `json`, `jsonb`, etc.).
- media fields: inferred from names like `image`, `primary_image`, `featured_image`, `gallery`.
- relationships: inferred from Eloquent relationships and `_id` properties when possible.
- `Collection<int, SomeResource>`: relationship when the model has a matching relation.

Use attributes for exceptions:

- `#[WordVelResource(...)]` for resource-level config such as `key`, menu grouping, ordering, slug prefix, or `includeId`.
- `#[OmitField]` to hide a backed field from the editor.
- `#[IncludeField]` to include fields normally omitted by convention.
- `#[RelationshipField]` when the public property name differs from the Eloquent relation.
- `#[SlugField]` for non-`slug` slug-like fields such as `code`.
- `#[NumberField(readonly: true)]` for computed totals.
- `#[MediaField]` when media column names do not follow convention.

## Blocks

Blocks use `#[AsBlock]`:

```php
#[AsBlock('hero', 'Hero')]
final class HeroBlockData extends Data
{
    public function __construct(
        #[Text]
        public string $title,

        #[Image]
        public ?MediaData $image = null,
    ) {}
}
```

Block fields still use explicit block attributes because block schemas are pure editor schemas and do not have an Eloquent model to infer from.

## Theme Options

Theme options use `#[AsThemeOptions]`:

```php
#[AsThemeOptions('site', 'Theme Options')]
final class SiteThemeOptionsResource extends Data
{
    public function __construct(
        #[OptionGroup('Brand')]
        public BrandOptionsData $brand,
    ) {}
}
```

WordPress renders the editor from the manifest and stores values in WordPress options.

## OpenAPI

WordVel expects API apps to use DTOs that can also be scanned by `langsys/openapi-docs-generator`. The OpenAPI generator scans code and annotations; it does not require WordVel bridge route metadata.

Common flow:

```bash
php artisan openapi:generate
```

## Local Development

Typical loop:

```bash
php artisan optimize:clear
php artisan wordvel:manifest
php artisan openapi:generate
php artisan test
```

When working on the WordPress bridge, also publish or copy bridge assets into the test app and confirm `/wp-json/wordvel/v1/...` routes proxy to Laravel over HTTP.

# Agent Notes

WordVel follows codebase-as-configuration. Prefer conventions and inference over adding new config knobs.

## Core Principles

- Laravel API code is the source of truth.
- WordPress must not bypass Laravel route/controller/middleware behavior for API routes.
- The WordPress bridge proxies API routes over HTTP using manifest `target_url`.
- Do not use route `response_dto` as a bridge marker. It is backward-compatible only.
- Do not add generated route metadata when an API-prefix convention can discover the route.
- Use explicit config only when convention cannot know the intent.

## Resource CoC

When editing `ResourceSchemaFactory`, preserve these defaults:

- infer model from `fromModel(Model $model)`;
- omit `id` unless `includeId` or `#[IncludeField]`;
- omit `*_at` unless `includeTimestamps` or `#[IncludeField]`;
- omit computed/unbacked fields from the WordPress editor;
- infer `string`, `int`, `float`, `bool`, enums, slug, textarea names, media names, localized JSON casts, and Eloquent relationships;
- let explicit field attributes override inference.

Use `#[RelationshipField]` only when the property name cannot map to a model relationship by convention.

## Bridge

Fresh bridge behavior should be HTTP proxying:

```txt
WordPress REST request -> wp_remote_request(target_url) -> Laravel API route
```

Do not reintroduce `WordvelApplication::dispatch()` into WordPress route callbacks for API routes. That bypasses Laravel HTTP behavior.

Resource editor scripts call the WordPress proxy at `/wp-json/wordvel-editor/v1/resources/...` with the login cookie and `X-WP-Nonce`. Never point browser code at `/api/wordvel/resources` directly: those routes require the `X-Wordvel-Bridge-Key` header that only the WordPress proxy sends (`Wordvel\Bridge\BridgeKey`, `VerifyBridgeKey` middleware).

## Installers

`wordvel:install` should:

- publish `config/wordvel.php`;
- ensure `routes/api.php`;
- install starter DTOs/controllers/routes;
- optionally install API Kit;
- optionally install WordPress bridge assets;
- optionally install ReDocly;
- run `wordvel:manifest`;
- run `openapi:generate` when available.

## Checks

Run from a consuming app after meaningful changes:

```bash
php artisan optimize:clear
php artisan wordvel:manifest
php artisan openapi:generate
php artisan test
```

Inspect `storage/wordvel/manifest.json`:

- API routes should have `target_url`.
- Path params should be WordPress regex params, e.g. `(?P<slug>[^/]+)`.
- Routes should not need `response_dto`.

Lint changed PHP files with `php -l`.

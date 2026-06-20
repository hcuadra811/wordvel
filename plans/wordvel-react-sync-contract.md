# Wordvel React Sync Contract Formalization

## Goal

Make `wordvel:sync` generic for React sites that follow the Wordvel pattern, so new sites do not require command edits for every new block, app shell, fixture shape, or preview edge case.

## Current Problem

The sync command has needed site-specific fixes for the first Wordvel site, Bruno Cortina, and Joyeria Belle. That means the implicit contract between a React app and Wordvel is still living in implementation guesses instead of a documented, testable boundary.

## Proposed Contract

### 1. Site Manifest

Each React site owns a `wordvel.config.mjs` file with an explicit schema:

- `entry`: preview/runtime entry file.
- `styles`: ordered CSS files or a single built CSS output.
- `blocks`: map of Wordvel block keys to preview components.
- `fixtures`: block fixture data used for sync previews, tests, and Gutenberg examples.
- `externals`: React packages that Wordvel provides and packages that must be bundled.
- `assets`: asset handling policy, including base URL rewrites and media placeholders.
- `api`: optional base URL and language defaults for blocks that fetch data.
- `previewDocument`: optional wrapper for global providers, layout chrome, theme classes, and root attributes.

The config should be validated before sync starts. Missing keys should fail with clear messages.

### 2. Block Preview Interface

Every preview component receives the same props:

- `data`: block DTO payload from Wordvel.
- `language`: current preview language.
- `site`: site/theme/header/footer context if needed.
- `fixtures`: local fixture helpers for non-production preview data.
- `mode`: `sync`, `editor`, or `runtime`.

Preview components must be pure React renderers. They should not depend on route state unless the route is passed through the wrapper.

### 3. DTO-Driven Fixtures

Wordvel should generate starter fixtures from PHP DTO metadata:

- localized fields become `{ en, es }` sample values.
- media fields become stable placeholder media objects.
- relationship fields become sample IDs plus optional hydrated sample objects.
- repeaters generate at least two representative rows.
- convention fields like `featured_image`, `gallery`, `categories`, and `excerpt` receive WordPress-like fixture labels.

Projects can override generated fixtures in `wordvel.config.mjs`, but should not need to define everything manually.

### 4. CSS and Markup Boundary

The sync output should preserve the app’s real CSS order and wrapper markup:

- Collect CSS from configured files or build manifest.
- Keep cascade order deterministic.
- Scope only when explicitly configured.
- Render previews inside the same root wrapper/classes used by the app.
- Support per-block wrapper overrides for blocks that need full-width or section-level context.

### 5. Data-Fetching Blocks

Blocks that fetch from APIs should declare a preview strategy:

- `fixture`: render local sample data during Gutenberg preview.
- `live`: call configured API endpoint in preview.
- `hybrid`: live in runtime, fixture in sync/editor.

This prevents catalog/search/list blocks from making sync slow or brittle.

### 6. Doctor Command

Add `wordvel:sync:doctor` to check:

- config schema validity.
- missing block mappings.
- missing fixtures.
- CSS file existence and order.
- React externals/bundling mismatches.
- preview render failures.
- API-dependent blocks without a preview strategy.

### 7. Golden Tests

Add fixtures and snapshot/golden tests for:

- a simple text block.
- nested repeater block.
- media block.
- relationship block.
- API-backed catalog block with fixture strategy.
- multilingual content switch.

The command should be tested against a tiny reference React app in CI.

## Migration Plan

1. Document the current informal config keys.
2. Add schema validation without changing behavior.
3. Add generated fixtures from DTO metadata.
4. Add preview strategies for data-fetching blocks.
5. Move site-specific assumptions into config adapters.
6. Add `wordvel:sync:doctor`.
7. Convert Wordvel site, Bruno Cortina, and Joyeria Belle to the formal contract.
8. Lock the command with golden tests.

## Open Questions

- Should block preview strategies live in PHP DTO attributes, React config, or both?
- Should Wordvel provide React as an external always, or should sites choose?
- Should sync store rendered HTML only, or also a structured preview manifest for Gutenberg?
- How much WordPress admin convention should be inferred from field names versus explicit DTO metadata?


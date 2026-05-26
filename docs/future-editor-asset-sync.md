# Future Feature: Editor Asset Sync

WordVel's adapter preview sync should eventually transfer local assets used by
editor previews, starting with local font files.

## Goal

Frontend developers should only write normal components and normal app CSS.
When `wordvel-react sync` runs, WordVel should make the Gutenberg editor preview
look like the app without requiring manual WordPress-specific asset setup.

## Proposed Flow

1. Build the frontend app.
2. `wordvel-react sync` reads the app document and built CSS.
3. The sync process detects asset URLs referenced by CSS, especially font files
   in `@font-face` declarations.
4. Local asset references are resolved against the React project and build
   output.
5. Assets are sent to Laravel with the editor preview artifact.
6. Laravel stores them under a WordVel editor asset directory.
7. The synced CSS is rewritten so WordPress loads those files from a WordVel
   editor asset endpoint.

## Initial Asset Types

- `.woff2`
- `.woff`
- `.ttf`
- `.otf`

## Later Asset Types

The same pipeline can later support images and other CSS assets used in preview
markup, such as background images.

## Notes

External stylesheet links, such as Google Fonts, should be resolved by
the adapter into usable CSS when possible. Local files should be uploaded and
served by WordVel instead of requiring the React developer to copy assets into
WordPress.

## Inline Editing Bridge

Adapter-generated previews currently render as static HTML in Gutenberg. That keeps
the canvas visually close to the real site, but it means text can only be edited
through the block inspector form.

Future work should let WordVel preserve inline editing for simple fields:

1. The frontend adapter emits stable markers around field placeholders in generated
   preview HTML.
2. The WordPress block runtime parses the generated HTML.
3. Text markers are replaced with Gutenberg editable controls such as `RichText`.
4. Complex fields, including images, icons, repeaters, and selects, continue to
   use inspector controls until dedicated canvas controls exist.

The goal is to keep component markup and CSS as the source of preview truth
while restoring direct Gutenberg canvas editing for plain text fields.

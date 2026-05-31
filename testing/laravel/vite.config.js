import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// Paths to the linked WordVel SDK packages (same layout as bruno_cortina)
const wordvelTypescript = resolve(__dirname, '../../../../../wordvel-typescript');
const wordvelReact = resolve(__dirname, '../../../../../wordvel-react');

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },

    // Protect against pulling Node-only code (e.g. "codex" tooling / fs usage)
    // from the linked @wordvel/* packages during dev — same pattern that fixed
    // the bruno_cortina site.
    resolve: {
        dedupe: ['react', 'react-dom'],
        alias: {
            '@wordvel/react': wordvelReact,
            '@wordvel/typescript': wordvelTypescript,
        },
    },

    optimizeDeps: {
        include: [
            '@wordvel/react',
            '@wordvel/typescript',
        ],
        force: true,
    },

    ssr: {
        noExternal: ['@wordvel/react', '@wordvel/typescript'],
    },
});

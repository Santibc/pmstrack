import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Las fuentes se descargan al compilar y se sirven desde el propio servidor (sin CDN).
            fonts: [
                bunny('Hanken Grotesk', { weights: [400, 500, 600, 700] }),
                bunny('Bricolage Grotesque', { weights: [600, 700, 800] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

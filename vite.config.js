import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Fraunces', { weights: [400, 600], styles: ['normal', 'italic'] }),
                bunny('Inter', { weights: [400, 500, 600] }),
            ],
        }),
    ],
    build: {
        cssMinify: true,
        rollupOptions: {
            output: { manualChunks: undefined },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

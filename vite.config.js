import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.js', 'resources/js/home.js'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Outfit', {
                    weights: [300, 400, 500],
                }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

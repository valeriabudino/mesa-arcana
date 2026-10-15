import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/mesa/laboratorio.js',
                'resources/js/mesa/mesa.js',
                'resources/js/espera/salaEspera.js',
                'resources/js/espera/panelAtencion.js',
                'resources/js/inicio/inicio.js',
            ],
            refresh: true,
            fonts: [
                // Se descargan en el build y se sirven desde la app: el navegador no le pide nada a terceros.
                bunny('Inter', { weights: [400, 500, 600] }),
                bunny('Cinzel', { weights: [500, 600] }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});

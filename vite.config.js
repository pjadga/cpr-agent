import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        host: "0.0.0.0",
        port: 5173,
        strictPort: true,
        origin: `${process.env.DDEV_PRIMARY_URL_WITHOUT_PORT}:5173`,
        cors: {
            origin: /https?:\/\/([A-Za-z0-9\-\.]+)?(\.ddev\.site)(?::\d+)?$/,
        },
        // Under DDEV the page is served from the DDEV hostname over https, so the HMR
        // socket must be wss on that same host (wss://localhost has no matching cert).
        // Outside DDEV leave Vite's defaults alone.
        ...(process.env.DDEV_HOSTNAME && {
            hmr: {protocol: 'wss', host: process.env.DDEV_HOSTNAME},
        }),
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/themes.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue()
    ],
    refresh: [
        'resources/routes/**',
        'routes/**',
        'resources/views/**',
        'resources/js/**',
    ],
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
});

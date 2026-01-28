import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true,
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/home-clock/focus-clock.css',
                'resources/js/home-clock/focus-clock.js',
                'resources/js/pico-timer-sync.js',
                'resources/css/rewards.css',
                'resources/js/rewards.js',
                'resources/js/statistics.js',
                'resources/js/profile.js',
                'resources/js/admin-dashboard.js'
            ],
            refresh: true,
        }),
    ],
});

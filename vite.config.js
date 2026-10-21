import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
            port: 5173,
        },
        watch: {
            usePolling: true,
            // Polling a Windows bind mount is expensive: skip trees Vite never
            // serves (vendor/ alone is ~14k files and stalled every request).
            ignored: [
                '**/storage/**',
                '**/vendor/**',
                '**/public/build/**',
                '**/.docker/**',
                '**/docs/**',
                '**/tests/**',
            ],
        },
    },
});

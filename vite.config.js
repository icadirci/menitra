import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Keep in sync with App\Enums\MenuTheme (enforced by tests/Feature/Architecture/FrontendBundlesTest.php).
const menuThemes = ['classic', 'minimal', 'bistro', 'cafe', 'grid', 'zen', 'coastal', 'lounge'];

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Public site (marketing + auth pages): Tailwind, Alpine, axios.
                'resources/css/app.css',
                'resources/js/app.js',
                // QR menus: base styles + one theme stylesheet + Alpine-only script.
                'resources/css/menu-base.css',
                'resources/js/menu.js',
                ...menuThemes.map((theme) => `resources/css/themes/${theme}.css`),
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Runs inside the `node` Docker service: listen on all interfaces and
        // tell the browser (and public/hot) to use the published host port.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

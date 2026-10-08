import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Assets are compiled locally (`npm run build`) into public/build and uploaded
// during deployment. Production never runs Node.js.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // An IPv4 origin (not [::1]) so the development CSP can allow it.
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

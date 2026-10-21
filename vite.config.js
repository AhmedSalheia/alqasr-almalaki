import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { existsSync } from 'fs';
import { resolve } from 'path';
import { pathToFileURL } from 'url';

// NativePHP's helper (routes HMR to the phone, sets the right APP_URL per platform).
const nativeHelper = resolve(process.cwd(), 'vendor/nativephp/mobile/resources/js/vite-plugin.js');
const native = existsSync(nativeHelper) ? await import(pathToFileURL(nativeHelper).href) : null;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            hotFile: native ? native.nativephpHotFile() : 'public/hot',
        }),
        tailwindcss(),
        ...(native ? [native.nativephpMobile()].flat() : []),
    ],
    build: {
        // Android 13+ System WebView (Chromium 111+) is required by Tailwind v4
        target: ['chrome111', 'safari16.4'],
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/nativephp/**'],
        },
    },
});

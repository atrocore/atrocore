import {defineConfig} from 'vite';
import {svelte} from '@sveltejs/vite-plugin-svelte';

export default defineConfig({
    plugins: [svelte()],
    base: '/client',
    build: {
        outDir: '../client',
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name === 'style.css') {
                        return 'css/style.css';
                    }
                    return 'assets/[name][extname]';
                }
            }
        },
        lib: {
            entry: './src/main.js',
            name: 'Svelte',
            formats: ['umd'],
            fileName: (format) => 'atro.min.js',
        }
    }
});

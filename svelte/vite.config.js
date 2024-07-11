import {defineConfig} from 'vite';
import {svelte} from '@sveltejs/vite-plugin-svelte';

export default defineConfig({
    plugins: [svelte()],
    build: {
        lib: {
            entry: './src/main.js',
            name: 'Svelte',
            formats: ['umd'],
            fileName: (format) => `svelte.${format}.js`
        }
    }
});

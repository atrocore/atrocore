import {resolve} from 'path';
import {defineConfig} from 'vite';
import {svelte} from '@sveltejs/vite-plugin-svelte';

export default defineConfig(({ command, mode }) => {
    const isWatch = process.argv.includes('--watch');

    return {
        plugins: [svelte()],
        base: '/client',
        build: {
            minify: !isWatch, // minify only when NOT in watch mode
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
                entry: './src/main.ts',
                name: 'Svelte',
                formats: ['umd'],
                fileName: (format) => 'atro.min.js',
            }
        },
        define: {
            'process.env.NODE_ENV': JSON.stringify('production'),
        },
        resolve: {
            alias: {
                $lib: resolve(__dirname, 'src/lib'),
                $assets: resolve('./src/assets')
            }
        }
    };
});
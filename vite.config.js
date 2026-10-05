import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import { phpBackendPlugin } from './scripts/vitePhpBackend';

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
const env = loadEnv(mode, process.cwd(), 'VITE_');
const externalBackend = process.env.VITE_PHP_BACKEND || env.VITE_PHP_BACKEND;
const backend = externalBackend || 'http://127.0.0.1:8087';
return {
  // Apache serves the production SPA from this project directory.
  // The explicit base keeps routes and built assets under the same URL prefix.
  base: process.env.NODE_ENV === 'production' ? '/app/' : '/',
  plugins: [react(), phpBackendPlugin(backend, Boolean(externalBackend))],
  // Vite must serve `public/` in development so files under public/assets
  // are available at /assets. Production already serves that directory via PHP.
  publicDir: process.env.NODE_ENV === 'production' ? false : 'public',
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    // Output the production build into public/app/ so Nginx/Apache can
    // serve it from the same document root as the PHP backend.
    outDir: 'public/app',
    emptyOutDir: true,
  },
  server: {
    port: 3000,
    open: true,
    proxy: {
      '/login': {
        target: backend,
        changeOrigin: true,
        bypass: (request) => request.method === 'GET' ? '/index.html' : undefined,
      },
      '/logout': {
        target: backend,
        changeOrigin: true,
      },
      '/csrf-token': {
        target: backend,
        changeOrigin: true,
      },
      '/api': {
        target: backend,
        changeOrigin: true,
      },
      '/change-password': {
        target: backend,
        changeOrigin: true,
      },
    },
  },
};
});

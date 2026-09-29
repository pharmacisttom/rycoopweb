import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

// https://vitejs.dev/config/
export default defineConfig({
  // Apache serves the production SPA from this project directory.
  // The explicit base keeps routes and built assets under the same URL prefix.
  base: process.env.NODE_ENV === 'production' ? '/app/' : '/',
  plugins: [react()],
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
        target: 'http://127.0.0.1',
        changeOrigin: true,
      },
      '/logout': {
        target: 'http://127.0.0.1',
        changeOrigin: true,
      },
      '/csrf-token': {
        target: 'http://127.0.0.1',
        changeOrigin: true,
      },
      '/api': {
        target: 'http://127.0.0.1',
        changeOrigin: true,
      },
      '/change-password': {
        target: 'http://127.0.0.1',
        changeOrigin: true,
      },
    },
  },
});

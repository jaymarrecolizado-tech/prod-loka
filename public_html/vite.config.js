import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'assets/js'),
      '~': resolve(__dirname, '.'),
      '@/components': resolve(__dirname, 'assets/js/components'),
      '@/composablesables': resolve(__dirname, 'assets/js/composablesables'),
      '@/utils': resolve(__dirname, 'assets/js/utils'),
      '@/stores': resolve(__dirname, 'assets/js/stores'),
      '@/api': resolve(__dirname, 'assets/js/api'),
    },
  },
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: resolve(__dirname, 'assets/js/app.js'),
        admin: resolve(__dirname, 'assets/js/admin.js'),
      },
      output: {
        entryFileNames: 'js/[name]-[hash].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name.endsWith('.css')) {
            return 'css/[name]-[hash][extname]'
          }
          return 'assets/[name]-[hash][extname]'
        },
      },
    },
    sourcemap: true,
  },
  server: {
    port: 5173,
    strictPort: false,
    host: true,
    origin: 'http://localhost:5173',
    hmr: {
      protocol: 'ws',
      host: 'localhost',
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./assets/js/test/setup.js'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
      include: ['assets/js/**/*.{js,ts,vue}'],
      exclude: ['assets/js/test/**', '**/*.config.js', '**/dist/**'],
    },
  },
})

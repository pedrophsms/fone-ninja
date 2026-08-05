import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import tsconfigPaths from 'vite-tsconfig-paths'

export default defineConfig({
  plugins: [vue(), tsconfigPaths()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    css: true,
    setupFiles: ['src/test/setup.ts'],
    server: {
      deps: {
        inline: ['vuetify'],
      },
    },
  },
})

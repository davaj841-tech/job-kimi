import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './resources/js'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['resources/js/tests/setup.ts'],
    include: ['resources/js/**/*.spec.ts'],
    // forks is more stable on Windows than threads (avoids pool worker timeouts)
    pool: 'forks',
    maxWorkers: 1,
    fileParallelism: false,
    isolate: true,
    testTimeout: 20000,
    hookTimeout: 20000,
    teardownTimeout: 15000,
    coverage: {
      provider: 'v8',
      reporter: ['text', 'lcov', 'html'],
      reportsDirectory: './coverage',
      include: [
        'resources/js/App.vue',
        'resources/js/stores/exam.ts',
        'resources/js/stores/auth.ts',
        'resources/js/components/LoginForm.vue',
        'resources/js/components/EmptyState.vue',
      ],
      thresholds: {
        lines: 60,
        functions: 60,
        branches: 50,
        statements: 60,
      },
    },
  },
})

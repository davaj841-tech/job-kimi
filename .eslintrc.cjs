/* eslint-env node */
module.exports = {
  root: true,
  extends: [
    'plugin:vue/vue3-recommended',
    '@vue/eslint-config-typescript',
    '@vue/eslint-config-prettier',
  ],
  parserOptions: { ecmaVersion: 'latest' },
  rules: {
    'vue/multi-word-component-names': 'off',
    '@typescript-eslint/no-explicit-any': 'warn',
    'no-console': 'off',
  },
  ignorePatterns: [
    'node_modules/',
    'vendor/',
    'public/',
    'storage/',
    'bootstrap/',
    'resources/js/views/**',
    'resources/js/admin/views/**',
    'resources/js/components/**',
    'resources/js/admin/components/**',
  ],
}

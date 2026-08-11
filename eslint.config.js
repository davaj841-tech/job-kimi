import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import ts from 'typescript-eslint'
import prettier from 'eslint-config-prettier'
import vueParser from 'vue-eslint-parser'
import globals from 'globals'

export default [
  {
    ignores: [
      'node_modules/**',
      'vendor/**',
      'public/**',
      'storage/**',
      'bootstrap/**',
      'dist/**',
      'coverage/**',
    ],
  },
  js.configs.recommended,
  ...ts.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['resources/js/**/*.{ts,vue,js}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.es2021,
      },
      parser: vueParser,
      parserOptions: {
        parser: ts.parser,
        extraFileExtensions: ['.vue'],
      },
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      'vue/no-v-html': 'warn',
      'no-undef': 'off',
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-unused-vars': [
        'error',
        {
          argsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
          caughtErrorsIgnorePattern: '^_',
        },
      ],
      'no-console': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
    },
  },
  prettier,
]

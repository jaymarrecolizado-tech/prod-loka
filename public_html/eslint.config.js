import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import * as parserVue from 'vue-eslint-parser'
import configPrettier from 'eslint-config-prettier'
import pluginPrettier from 'eslint-plugin-prettier'
import globals from 'globals'

export default [
  {
    name: 'app/files-to-lint',
    files: ['**/*.{ts,mts,tsx,vue}'],
  },

  {
    name: 'app/files-to-ignore',
    ignores: [
      '**/dist/**',
      '**/dist-ssr/**',
      '**/coverage/**',
      'node_modules/**',
      'vendor/**',
      'assets/dist/**',
      'assets/js/all.min.js',
    ],
  },

  js.configs.recommended,
  ...pluginVue.configs['flat/essential'],
  configPrettier,

  {
    name: 'app/node-configs',
    files: ['**/*.config.js', 'test/**/*.js', 'assets/js/test/**/*.js', 'cron/**/*.js'],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
    plugins: {
      prettier: pluginPrettier,
    },
    rules: {
      'prettier/prettier': 'warn',
    },
  },

  {
    name: 'app/source-files',
    plugins: {
      prettier: pluginPrettier,
    },
    rules: {
      'prettier/prettier': 'warn',
      'no-console': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
      'no-debugger': process.env.NODE_ENV === 'production' ? 'error' : 'off',
      'vue/multi-word-component-names': 'off',
      'vue/no-v-html': 'warn',
      'no-unused-vars': [
        'warn',
        {
          varsIgnorePattern: '^(api|viewAll|showToast)$',
        },
      ],
    },
    languageOptions: {
      parser: parserVue,
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
        extraFileExtensions: ['.vue'],
      },
      globals: {
        // Browser globals
        window: 'readonly',
        document: 'readonly',
        navigator: 'readonly',
        localStorage: 'readonly',
        sessionStorage: 'readonly',
        fetch: 'readonly',
        URL: 'readonly',
        FormData: 'readonly',
        // Legacy globals (loaded via script tags)
        $: 'readonly',
        jQuery: 'readonly',
        flatpickr: 'readonly',
        bootstrap: 'readonly',
        // Custom globals
        LOKA: 'readonly',
        API_BASE: 'readonly',
      },
    },
  },
]

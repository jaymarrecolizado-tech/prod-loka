/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.php',
    './pages/**/*.php',
    './includes/**/*.php',
    './assets/js/**/*.vue',
    './assets/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f0f9ff',
          100: '#e0f2fe',
          200: '#bae6fd',
          300: '#7dd3fc',
          400: '#38bdf8',
          500: '#0ea5e9',
          600: '#0284c7',
          700: '#0369a1',
          800: '#075985',
          900: '#0c4a6e',
          950: '#082f49',
        },
        success: {
          50: '#f0fdf4',
          100: '#dcfce7',
          500: '#22c55e',
          600: '#16a34a',
          700: '#15803d',
        },
        warning: {
          50: '#fffbeb',
          100: '#fef3c7',
          500: '#f59e0b',
          600: '#d97706',
        },
        danger: {
          50: '#fef2f2',
          100: '#fee2e2',
          500: '#ef4444',
          600: '#dc2626',
          700: '#b91c1c',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Consolas', 'monospace'],
      },
      spacing: {
        18: '4.5rem',
        88: '22rem',
        128: '32rem',
        navbar: '3.5rem',
        sidebar: '16rem',
      },
      borderRadius: {
        '2xl': '1rem',
        '3xl': '1.5rem',
      },
      boxShadow: {
        soft: '0 2px 15px -3px rgba(0, 0, 0, 0.07), 0 10px 20px -2px rgba(0, 0, 0, 0.04)',
        card: '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05)',
        elevated: '0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.05)',
      },
      transitionTimingFunction: {
        soft: 'cubic-bezier(0.4, 0, 0.2, 1)',
      },
      animation: {
        'fade-in': 'fadeIn 0.3s ease-in-out',
        'slide-in': 'slideIn 0.3s ease-out',
        'slide-out': 'slideOut 0.3s ease-in',
        'spin-slow': 'spin 3s linear infinite',
        'bounce-slow': 'bounce 2s infinite',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideIn: {
          '0%': { transform: 'translateY(-10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        slideOut: {
          '0%': { transform: 'translateY(0)', opacity: '1' },
          '100%': { transform: 'translateY(-10px)', opacity: '0' },
        },
      },
    },
  },
  plugins: [require('@tailwindcss/forms'), require('@tailwindcss/typography'), require('daisyui')],

  daisyui: {
    themes: [
      {
        // Navy Cyan — sleek dark theme matching login screen palette
        loka: {
          'primary': '#00d4ff',           // cyan — primary CTAs
          'primary-content': '#041018',
          'secondary': '#60a5fa',         // blue-400 — readable on navy
          'secondary-content': '#041018',
          'accent': '#22d3ee',            // light cyan accent
          'accent-content': '#041018',
          'neutral': '#0c1f4a',
          'neutral-content': '#eef4ff',
          'base-100': '#0a0e1a',          // navy — page bg
          'base-200': '#0d1526',          // navy2 — cards
          'base-300': '#162033',          // borders
          'base-content': '#eef4ff',
          'info': '#38bdf8',
          'info-content': '#041018',
          'success': '#14e0b0',
          'success-content': '#041018',
          'warning': '#f5c518',
          'warning-content': '#1a1400',
          'error': '#f43f5e',
          'error-content': '#ffffff',
        },
      },
      {
        // Ghost White — soft, warm light theme with saddle undertones
        'loka-light': {
          'primary': '#1d4ed8',           // blue-700 — stronger primary
          'primary-content': '#ffffff',
          'secondary': '#0f766e',         // teal-700 — clear contrast vs primary
          'secondary-content': '#ffffff',
          'accent': '#0891b2',            // cyan-600
          'accent-content': '#ffffff',
          'neutral': '#334155',           // slate-700
          'neutral-content': '#f8fafc',
          'base-100': '#f8f7f4',          // ghost white — page bg
          'base-200': '#f0efeb',          // cards — slightly darker
          'base-300': '#e0ded8',          // borders — visible
          'base-content': '#1e293b',      // slate-800 — body text
          'info': '#0369a1',
          'info-content': '#ffffff',
          'success': '#15803d',
          'success-content': '#ffffff',
          'warning': '#c2410c',
          'warning-content': '#ffffff',
          'error': '#b91c1c',
          'error-content': '#ffffff',
        },
      },
    ],
    darkTheme: false,
    base: true,
    styled: true,
    utils: true,
    logs: false,
  },
}

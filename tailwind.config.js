import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Estedad Variable"', 'Estedad', 'Vazirmatn', 'Tahoma', ...defaultTheme.fontFamily.sans],
                display: ['"Estedad Variable"', 'Estedad', 'Vazirmatn', 'Tahoma', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#fef2f2',
                    100: '#fee2e2',
                    200: '#fecaca',
                    300: '#fca5a5',
                    400: '#f87171',
                    500: '#ef394e',
                    600: '#ef394e',
                    700: '#d32f41',
                    800: '#b91c1c',
                    900: '#991b1b',
                },
                brand: {
                    DEFAULT: 'rgb(var(--c-brand) / <alpha-value>)',
                    soft: 'rgb(var(--c-brand-soft) / <alpha-value>)',
                    dark: 'rgb(var(--c-brand-dark) / <alpha-value>)',
                },
                desk: {
                    dark: 'rgb(var(--c-ink) / <alpha-value>)',
                    blue: 'rgb(var(--c-ink-2) / <alpha-value>)',
                    orange: 'rgb(var(--c-accent) / <alpha-value>)',
                    green: '#22c55e',
                    gray: 'rgb(var(--c-page) / <alpha-value>)',
                    text: '#1e293b',
                    muted: '#64748b',
                },
                ink: {
                    DEFAULT: '#1e293b',
                    muted: '#64748b',
                    soft: '#475569',
                },
                surface: {
                    DEFAULT: '#ffffff',
                    page: 'rgb(var(--c-page) / <alpha-value>)',
                    line: 'rgb(var(--c-line) / <alpha-value>)',
                },
            },
            boxShadow: {
                card: '0 1px 4px rgba(0,0,0,.08)',
                nav: '0 -2px 10px rgba(0,0,0,.06)',
                desk: '0 1px 3px rgba(15,39,68,.08)',
            },
            maxWidth: {
                app: '480px',
            },
            aspectRatio: {
                '16/9': '16 / 9',
            },
        },
    },
    plugins: [],
};

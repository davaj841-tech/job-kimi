import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
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
                sans: ['Vazirmatn', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#ef394e',
                    soft: '#fff1f2',
                    dark: '#d32f41',
                },
                desk: {
                    dark: '#0f2744',
                    blue: '#1e3a5f',
                    orange: '#f97316',
                    green: '#22c55e',
                    gray: '#f8fafc',
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
                    page: '#f8fafc',
                    line: '#e2e8f0',
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

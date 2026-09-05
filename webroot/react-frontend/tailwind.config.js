/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ['./src/**/*.{js,jsx,ts,tsx}', './public/index.html'],
    theme: {
        extend: {
            colors: {
                // CareerPass brand color — matches Bulma's default palette
                // (primary turquoise #00D1B2), so it reads as a familiar
                // "Bulma-style" accent even though the framework is Tailwind.
                brand: {
                    DEFAULT: '#00D1B2',
                    dark: '#009E86',
                    light: '#CCFFF7',
                },
            },
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
            },
            keyframes: {
                drift1: {
                    '0%, 100%': { transform: 'translate(0, 0)' },
                    '25%': { transform: 'translate(100px, 150px)' },
                    '50%': { transform: 'translate(200px, -100px)' },
                    '75%': { transform: 'translate(-50px, 50px)' },
                },
                drift2: {
                    '0%, 100%': { transform: 'translate(0, 0)' },
                    '25%': { transform: 'translate(-100px, -150px)' },
                    '50%': { transform: 'translate(-200px, 100px)' },
                    '75%': { transform: 'translate(50px, -50px)' },
                },
            },
            animation: {
                drift1: 'drift1 20s ease-in-out infinite alternate',
                drift2: 'drift2 25s ease-in-out infinite alternate',
            },
        },
    },
    plugins: [],
};

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ['./src/**/*.{js,jsx,ts,tsx}', './public/index.html'],
    theme: {
        extend: {
            colors: {
                // SkillSprint brand teal used throughout the app
                brand: {
                    DEFAULT: '#14B8A6',
                    dark: '#0D9488',
                    light: '#5EEAD4',
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

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ['./src/**/*.{js,jsx,ts,tsx}', './public/index.html'],
    darkMode: 'class',
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
                // Hand-drawn annotation font (see <HandNote>), loaded in public/index.html
                hand: ['Caveat', 'ui-sans-serif', 'cursive'],
            },
            keyframes: {
                pop: {
                    '0%': { transform: 'scale(0.96)' },
                    '55%': { transform: 'scale(1.03)' },
                    '100%': { transform: 'scale(1)' },
                },
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
                // Splash screen: the logo bars breathe like an equalizer.
                'bar-rise': {
                    '0%, 100%': { transform: 'skewX(-14deg) scaleY(0.7)' },
                    '50%': { transform: 'skewX(-14deg) scaleY(1)' },
                },
                'loader-sweep': {
                    '0%': { transform: 'translateX(-120%)' },
                    '100%': { transform: 'translateX(520%)' },
                },
            },
            animation: {
                pop: 'pop 0.35s ease-out',
                drift1: 'drift1 20s ease-in-out infinite alternate',
                drift2: 'drift2 25s ease-in-out infinite alternate',
                'bar-rise': 'bar-rise 1.1s ease-in-out infinite',
                'loader-sweep': 'loader-sweep 1.2s ease-in-out infinite',
            },
        },
    },
    plugins: [],
};

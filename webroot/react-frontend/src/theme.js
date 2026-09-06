/**
 * Light/dark theme micro-store. The `dark` class on <html> is what Tailwind's
 * `dark:` variants key off (darkMode: 'class' in tailwind.config.js).
 *
 * An inline script in public/index.html applies the same resolution before the
 * first paint to avoid a flash — keep THEME_KEY and the order in sync with it.
 *
 * Components read the current theme with useSyncExternalStore(subscribe, getCurrentTheme)
 * so every mounted <ThemeToggle> (header, mobile menu, ...) stays in agreement.
 */
export const THEME_KEY = 'careerpass_theme';

const listeners = new Set();

/** Resolve the theme from scratch: saved choice → system preference → light. */
function resolveTheme() {
    try {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved === 'dark' || saved === 'light') return saved;
    } catch (e) {
        /* localStorage unavailable */
    }
    try {
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
    } catch (e) {
        /* matchMedia unavailable */
    }
    return 'light';
}

let current = resolveTheme();

function apply(theme) {
    try {
        document.documentElement.classList.toggle('dark', theme === 'dark');
    } catch (e) {
        /* no document (tests) */
    }
}

// Keep the DOM in agreement on load even if the inline script didn't run.
apply(current);

export function getCurrentTheme() {
    return current;
}

export function setTheme(theme) {
    if (theme !== 'dark' && theme !== 'light') return;
    current = theme;
    apply(theme);
    try {
        localStorage.setItem(THEME_KEY, theme);
    } catch (e) {
        /* ignore persistence failure */
    }
    listeners.forEach((l) => l(theme));
}

export function toggleTheme() {
    setTheme(current === 'dark' ? 'light' : 'dark');
}

/** Subscribe to theme changes; returns an unsubscribe fn. */
export function subscribe(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

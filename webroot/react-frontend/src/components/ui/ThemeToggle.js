import React, { useSyncExternalStore } from 'react';
import { FaMoon, FaSun } from 'react-icons/fa6';
import { getCurrentTheme, subscribe, toggleTheme } from '../../theme';

/**
 * Sun/moon theme switch. Drop it into a header/nav next to the other actions.
 * All mounted instances share state via the theme store, so the icon stays in
 * sync wherever it appears.
 */
export default function ThemeToggle({ className = '' }) {
    const theme = useSyncExternalStore(subscribe, getCurrentTheme, getCurrentTheme);
    const isDark = theme === 'dark';

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            title={isDark ? 'Light mode' : 'Dark mode'}
            className={`inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-300
                text-gray-600 transition-colors hover:bg-gray-100
                dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800 ${className}`}
        >
            {isDark ? <FaSun size={16} /> : <FaMoon size={16} />}
        </button>
    );
}

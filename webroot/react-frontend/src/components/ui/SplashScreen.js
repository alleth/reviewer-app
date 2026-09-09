import React from 'react';

/**
 * Branded full-screen loader shown while the app resolves auth state
 * (`index.js`, `isLoggedIn === null`) — replaces a bare "Loading…".
 *
 * Fades in after ~150ms (see the `splash-in` animation delay), so the common
 * fast-resolve case shows nothing rather than a flicker.
 */
export default function SplashScreen() {
    const bar = {
        width: '0.085em',
        background: '#fff',
        borderRadius: '0.045em',
        transformOrigin: 'bottom',
    };

    return (
        <div
            role="status"
            aria-live="polite"
            className="flex min-h-screen flex-col items-center justify-center gap-7 bg-gray-50 animate-splash-in dark:bg-gray-900"
        >
            <div className="flex flex-col items-center gap-4">
                {/* The CareerPass mark — three ascending bars, breathing while we load. */}
                <div
                    className="flex items-center justify-center"
                    style={{
                        fontSize: 54,
                        width: '1em',
                        height: '1em',
                        borderRadius: '0.18em',
                        background: '#00D1B2',
                        gap: '0.075em',
                        boxShadow: '0 12px 34px -10px rgba(0, 209, 178, 0.55)',
                    }}
                >
                    <span
                        className="animate-bar-rise motion-reduce:animate-none"
                        style={{ ...bar, height: '0.30em', opacity: 0.55, animationDelay: '0ms' }}
                    />
                    <span
                        className="animate-bar-rise motion-reduce:animate-none"
                        style={{ ...bar, height: '0.42em', opacity: 0.8, animationDelay: '150ms' }}
                    />
                    <span
                        className="animate-bar-rise motion-reduce:animate-none"
                        style={{ ...bar, height: '0.54em', opacity: 1, animationDelay: '300ms' }}
                    />
                </div>

                <span
                    className="text-2xl font-bold"
                    style={{ color: '#00C4A7', letterSpacing: '-0.035em' }}
                >
                    CareerPass
                </span>
            </div>

            {/* Indeterminate progress track. */}
            <div className="relative h-[3px] w-40 overflow-hidden rounded-full bg-brand/15 dark:bg-brand/25">
                <div className="absolute inset-y-0 w-1/4 rounded-full bg-brand animate-loader-sweep motion-reduce:hidden" />
            </div>

            <span className="sr-only">Loading CareerPass…</span>
        </div>
    );
}

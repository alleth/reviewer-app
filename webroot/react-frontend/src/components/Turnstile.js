import React, { useEffect, useRef } from 'react';

// Cloudflare Turnstile (H4) on sign-up and forgot password. With no site key
// (local dev) nothing renders and the API, without a secret, skips the check.
const SITE_KEY = process.env.REACT_APP_TURNSTILE_SITE_KEY || '';
const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

export const turnstileEnabled = SITE_KEY !== '';

let scriptPromise = null;
function loadScript() {
    if (window.turnstile) return Promise.resolve();
    if (!scriptPromise) {
        scriptPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = SCRIPT_URL;
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => {
                scriptPromise = null;
                reject(new Error('Turnstile failed to load'));
            };
            document.head.appendChild(script);
        });
    }
    return scriptPromise;
}

/**
 * Renders the widget and reports its token through `onToken` ('' when it
 * expires or fails). Tokens are single-use: remount it (change its `key`)
 * after each submit to get a fresh one.
 */
export default function Turnstile({ onToken }) {
    const container = useRef(null);
    const callback = useRef(onToken);
    callback.current = onToken;

    useEffect(() => {
        if (!turnstileEnabled) return undefined;
        let widgetId = null;
        let cancelled = false;

        loadScript()
            .then(() => {
                if (cancelled || !container.current) return;
                widgetId = window.turnstile.render(container.current, {
                    sitekey: SITE_KEY,
                    callback: (token) => callback.current(token),
                    'expired-callback': () => callback.current(''),
                    'error-callback': () => callback.current(''),
                });
            })
            // If the script can't load, the API fails open on its side only
            // when Cloudflare is unreachable for it too; the form shows the
            // server's message either way.
            .catch(() => callback.current(''));

        return () => {
            cancelled = true;
            if (widgetId !== null && window.turnstile) window.turnstile.remove(widgetId);
        };
    }, []);

    if (!turnstileEnabled) return null;
    return <div ref={container} className="flex justify-center" />;
}

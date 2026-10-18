import { useCallback, useEffect, useState } from 'react';
import { retryAfterSeconds, waitMessage } from '../rateLimit';

/**
 * Countdown for rate-limited forms (H4). `start(err)` returns true (and starts
 * counting down) when `err` is a 429, so a catch block can `return` early;
 * while `active`, show `message` and disable the submit button.
 */
export default function useRateLimit() {
    const [until, setUntil] = useState(0);
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (until <= Date.now()) return undefined;
        const timer = setInterval(() => setNow(Date.now()), 500);
        return () => clearInterval(timer);
    }, [until]);

    const start = useCallback((err) => {
        const seconds = retryAfterSeconds(err);
        if (seconds === null) return false;
        const at = Date.now();
        setNow(at);
        setUntil(at + seconds * 1000);
        return true;
    }, []);

    const seconds = Math.max(0, Math.ceil((until - now) / 1000));
    const active = seconds > 0;

    return { active, seconds, start, message: active ? waitMessage(seconds) : '' };
}

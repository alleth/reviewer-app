// What a rate-limited request means for the UI (H4). The API answers a
// throttled request with 429 { reason: 'rate_limited', retryAfter, message }
// and a Retry-After header. Plain functions (no React, no axios) so they can
// be unit-tested on their own — see rateLimit.test.js.

/**
 * Seconds to wait if `error` (an axios error) is a rate-limit refusal, else
 * null. A 429 without a usable number waits a minute.
 */
export function retryAfterSeconds(error) {
    const response = error?.response;
    if (!response || response.status !== 429) return null;

    const raw = response.data?.retryAfter ?? response.headers?.['retry-after'];
    const seconds = Number(raw);
    return Number.isFinite(seconds) && seconds > 0 ? Math.ceil(seconds) : 60;
}

/** 27 → "0:27", 900 → "15:00". */
export function formatWait(seconds) {
    const s = Math.max(0, Math.ceil(seconds));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** The message shown while a countdown runs. */
export function waitMessage(seconds) {
    return `Too many attempts. Please try again in ${formatWait(seconds)}.`;
}

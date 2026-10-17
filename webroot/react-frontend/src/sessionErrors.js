/**
 * How the SPA reads the API's session/pass denials (H1). Plain functions with
 * no axios import, so they can be unit-tested on their own (sessionErrors.test.js).
 *
 * The backend answers paid-content requests (`/api/topics…`, `/api/questions…`)
 * with:
 *   401 { reason: 'signed_out' | 'signed_in_elsewhere' }  — the session is dead
 *   403 { reason: 'no_active_pass' }                      — signed in, no pass
 */

/** sessionStorage key the landing page reads to explain a sign-out. */
export const SIGNED_OUT_KEY = 'careerpass_signed_out';

const DEAD_SESSION_REASONS = ['signed_out', 'signed_in_elsewhere'];

// Only the content endpoints. Other 401s — a failed Google sign-in, checkout,
// account edits, purchases — are handled where they happen and must not sign
// the user out from here.
const CONTENT_PATH = /^\/api\/(topics|questions)(\/|\?|$)/;

/** The request path of a failed axios call, without origin or base path. */
function requestPath(error) {
    const url = error?.config?.url || '';
    const path = url.replace(/^https?:\/\/[^/]+/, '');
    const i = path.indexOf('/api/');
    return i >= 0 ? path.slice(i) : path;
}

/**
 * `'signed_out'` or `'signed_in_elsewhere'` when `error` is a 401 from a
 * content endpoint that says the session is dead; otherwise null.
 */
export function deadSessionReason(error) {
    const res = error?.response;
    if (!res || res.status !== 401) return null;
    if (!CONTENT_PATH.test(requestPath(error))) return null;
    const reason = res.data?.reason;
    return DEAD_SESSION_REASONS.includes(reason) ? reason : null;
}

/** True when `error` is the 403 for a signed-in user without an active pass. */
export function isNoActivePass(error) {
    const res = error?.response;
    return Boolean(res && res.status === 403 && res.data?.reason === 'no_active_pass');
}

/**
 * The landing-page notice for a sign-out: `'elsewhere'` when the account
 * signed in on another device, `'invalid'` for any other dead session.
 */
export function signedOutNotice(reason) {
    return reason === 'signed_in_elsewhere' ? 'elsewhere' : 'invalid';
}

/** Records why this device was signed out, for the landing page to show once. */
export function rememberSignedOut(reason) {
    try {
        sessionStorage.setItem(SIGNED_OUT_KEY, signedOutNotice(reason));
    } catch (e) { /* storage unavailable — the redirect still happens */ }
}

import axios from 'axios';

const API_URL = process.env.REACT_APP_API_URL || 'http://localhost/reviewer_app';

/**
 * Shared axios instance for the state-changing auth endpoints (login, register, logout,
 * google-login, account/setup). Always sends X-Requested-With, credentials included.
 *
 * The backend rejects those endpoints without that header — it's a lightweight CSRF gate.
 * A plain HTML <form> POST (the classic CSRF vector) can't set custom headers, so this
 * forces any caller onto fetch/XHR, which triggers a CORS preflight that CorsMiddleware
 * only allows for FRONTEND_URL. See UsersController::requireAjaxHeader() for the other
 * half of this. Use this instance (not a bare axios/fetch call) for any new call to those
 * endpoints, or the request will get a 403.
 */
export const api = axios.create({
    baseURL: API_URL,
    withCredentials: true,
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

/**
 * Starts a Xendit checkout for one access pass and redirects the browser to the
 * hosted invoice page. Throws (with a `.userMessage`) if the invoice can't be
 * created so the caller can re-enable its button and show the error.
 */
export async function startCheckout(reviewer, planId) {
    try {
        const res = await api.post('/api/checkout', { reviewer, plan_id: planId });
        if (res.data?.invoice_url) {
            window.location.assign(res.data.invoice_url);
            return;
        }
        throw new Error(res.data?.message || 'Could not start checkout.');
    } catch (err) {
        const e = new Error(
            err.response?.data?.message || err.message || 'Could not start checkout.',
        );
        e.userMessage = e.message;
        throw e;
    }
}

/** The logged-in user's active access passes (post-checkout poll on /checkout/success). */
export function fetchPurchases() {
    return api.get('/api/purchases').then((res) => res.data?.purchases || []);
}

/** Every pass the user has paid for — payment history + receipts in Settings. */
export function fetchBillingHistory() {
    return api.get('/api/billing/history').then((res) => res.data?.purchases || []);
}

/** Reviewer topics (for the practice topic picker). */
export function fetchTopics() {
    return api.get('/api/topics').then((res) => res.data?.data || []);
}

/** A randomized practice set. `topicId` optional; `limit` 1–50. */
export function fetchPractice({ topicId, limit = 20 } = {}) {
    const params = { limit };
    if (topicId) params.topic_id = topicId;
    return api.get('/api/questions/practice', { params }).then((res) => res.data?.data || []);
}

export default API_URL;

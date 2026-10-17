import axios from 'axios';
import { deadSessionReason, rememberSignedOut } from './sessionErrors';

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

// A paid-content request answered 401 means this device's session is dead —
// usually because the account signed in on another device (H1). Sign out here
// and go to the landing page, which explains why. Only content-endpoint 401s
// count (see deadSessionReason); every other failure is left to its caller.
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const reason = deadSessionReason(error);
        if (reason) {
            try {
                localStorage.removeItem('skillsprint_user');
            } catch (e) { /* ignore */ }
            rememberSignedOut(reason);
            window.location.assign('/');
        }
        return Promise.reject(error);
    },
);

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

/** One topic, with its study/explainer write-up (`topic_review`, null if not authored yet). */
export function fetchTopic(topicId) {
    return api.get(`/api/topics/${topicId}`).then((res) => res.data?.data);
}

/**
 * A randomized practice set. `topicId` optional; `limit` 1–50. The questions
 * carry no answer key or explanations (H2) — check each answer with
 * submitAnswer().
 */
export function fetchPractice({ topicId, limit = 20 } = {}) {
    const params = { limit };
    if (topicId) params.topic_id = topicId;
    return api.get('/api/questions/practice', { params }).then((res) => res.data?.data || []);
}

/**
 * Checks one answer on the server (H2). Resolves to
 * `{ correct, correct_choice_id, explanation }`. Only works for questions this
 * session was served by fetchPractice().
 */
export function submitAnswer(questionId, choiceId) {
    return api
        .post(`/api/questions/${questionId}/answer`, { choice_id: choiceId })
        .then((res) => res.data);
}

export default API_URL;

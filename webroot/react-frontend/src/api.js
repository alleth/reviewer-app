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

export default API_URL;

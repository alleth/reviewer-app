<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * The one shared copy of the app's manual auth helpers (H1), used by the root
 * AppController so every controller — Users, Payments and the Api\ content
 * controllers — checks sessions the same way.
 *
 * - activeUserId(): the session user, only if the session is still the
 *   account's current one (single-device `session_token` check) and the
 *   account's email is verified; otherwise null, with the reason in
 *   $sessionDenialReason.
 * - notLoggedIn(): the standard 401 for a failed activeUserId().
 * - requireAjaxHeader(): the per-action copy of the CSRF gate (the middleware
 *   AjaxHeaderMiddleware enforces it for every state-changing request).
 * - json(): a JSON response.
 */
trait SessionAuthTrait
{
    /**
     * Why the last activeUserId() call returned null: `signed_out` (no session,
     * or one that can no longer be used) or `signed_in_elsewhere` (the account
     * has since signed in on another device, or had its password changed or
     * reset). Null after a successful call.
     */
    protected ?string $sessionDenialReason = null;

    /**
     * The logged-in user's id, or null when there's no usable session. A
     * session superseded by a login elsewhere is cleared here. Sessions created
     * before session_token existed (neither side has a token) are grandfathered
     * in until their next login. An account whose email isn't verified never
     * passes (B3-V backstop: no code path should give one a session).
     */
    protected function activeUserId(): ?int
    {
        $this->sessionDenialReason = null;
        $session = $this->request->getSession();
        $user = $session->read('Auth.User');
        if (!$user) {
            $this->sessionDenialReason = 'signed_out';

            return null;
        }

        $sessionToken = $session->read('Auth.token');
        /** @var \App\Model\Entity\User|null $current */
        $current = $this->fetchTable('Users')->find()
            ->select(['user_id', 'session_token', 'email_verified_at'])
            ->where(['user_id' => $user->user_id])
            ->first();

        $reason = null;
        if ($current === null || !$current->isEmailVerified()) {
            $reason = 'signed_out';
        } elseif ($sessionToken === null && $current->session_token === null) {
            // Pre-rollout session (neither side has a token) — leave it be.
            return (int)$user->user_id;
        } elseif ($sessionToken === null || $sessionToken !== $current->session_token) {
            $reason = 'signed_in_elsewhere';
        }

        if ($reason !== null) {
            $session->delete('Auth.User');
            $session->delete('Auth.token');
            $session->renew();
            $this->sessionDenialReason = $reason;

            return null;
        }

        return (int)$user->user_id;
    }

    /**
     * The 401 for a request that needs a session it doesn't have. `reason` says
     * which kind, so the SPA can tell a dead session from other failures.
     */
    protected function notLoggedIn(): Response
    {
        return $this->json([
            'success' => false,
            'message' => 'Not logged in',
            'reason' => $this->sessionDenialReason ?? 'signed_out',
        ], 401);
    }

    /**
     * Per-action copy of the CSRF check that AjaxHeaderMiddleware applies to
     * every state-changing request (H6); kept as a second layer.
     *
     * A plain HTML form cannot set custom request headers, only fetch/XHR can —
     * and a cross-origin fetch/XHR triggers a CORS preflight, which
     * CorsMiddleware only allows for FRONTEND_URL. So requiring this header
     * turns the CORS policy we already have into an effective CSRF check,
     * without needing a separate token scheme.
     *
     * @return \Cake\Http\Response|null A 403 response if the header is missing, null to continue.
     */
    protected function requireAjaxHeader(): ?Response
    {
        if ($this->request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest') {
            return $this->json(['success' => false, 'message' => 'Missing required request header'], 403);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     * @param int $status HTTP status.
     * @return \Cake\Http\Response
     */
    protected function json(array $body, int $status = 200): Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody((string)json_encode($body));
    }
}

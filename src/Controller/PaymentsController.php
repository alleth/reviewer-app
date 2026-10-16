<?php
declare(strict_types=1);

namespace App\Controller;

use App\Payment\Plans;
use App\Payment\Xendit;
use App\Payment\XenditException;
use App\Service\PassService;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Throwable;

/**
 * Access-pass checkout via Xendit. Manual auth (session `Auth.User` +
 * `session_token` guard), same as UsersController. See the CreatePasses
 * migration and CLAUDE.md → Deployment for the Xendit env vars and webhook URL.
 */
class PaymentsController extends AppController
{
    /** Reviewers a pass can be bought for (per-reviewer entitlements). */
    private const REVIEWERS = ['civil-service'];

    /**
     * POST /api/checkout — body { reviewer, plan_id }. Reuses a very-recent
     * still-pending invoice for the same (user, reviewer, plan) if one exists
     * (double-click / extra tab / retry guard); otherwise creates a pending
     * pass + a Xendit hosted invoice. Returns `invoice_url` for the SPA to
     * redirect to either way.
     */
    public function checkout(): Response
    {
        $this->request->allowMethod(['post']);
        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $userId = $this->activeUserId();
        if (!$userId) {
            return $this->json(['success' => false, 'message' => 'Not logged in'], 401);
        }

        try {
            $data = $this->request->getData();
            $reviewer = (string)($data['reviewer'] ?? '');
            $planId = (string)($data['plan_id'] ?? '');

            if (!in_array($reviewer, self::REVIEWERS, true)) {
                return $this->json(['success' => false, 'message' => 'Unknown reviewer.'], 422);
            }
            $plan = Plans::get($planId);
            if ($plan === null) {
                return $this->json(['success' => false, 'message' => 'Unknown plan.'], 422);
            }

            /** @var \App\Model\Table\PassesTable $passes */
            $passes = $this->fetchTable('Passes');

            // Reuse a very-recent still-pending invoice for the same
            // (user, reviewer, plan) instead of creating a second one — guards
            // against a double-click, an extra tab, or a client-side retry.
            // The window is short so a genuine repeat purchase later isn't
            // blocked, and a paid/expired/superseded pass never matches
            // `pending` so it can never accidentally short-circuit a new buy.
            $recent = $passes->find()
                ->where([
                    'user_id' => $userId,
                    'reviewer' => $reviewer,
                    'plan_id' => $planId,
                    'status' => 'pending',
                    'invoice_url IS NOT' => null,
                    'created >' => (new DateTime())->subMinutes(5),
                ])
                ->orderBy(['created' => 'DESC'])
                ->first();
            if ($recent !== null) {
                return $this->json(['success' => true, 'invoice_url' => $recent->invoice_url]);
            }

            $user = $this->fetchTable('Users')->get($userId);

            /** @var \App\Model\Entity\Pass $pass */
            $pass = $passes->newEntity([
                'user_id' => $userId,
                'reviewer' => $reviewer,
                'plan_id' => $planId,
                'amount' => number_format($plan['price'], 2, '.', ''),
                'currency' => 'PHP',
                'external_id' => 'cp_' . bin2hex(random_bytes(16)),
                'status' => 'pending',
            ]);
            if (!$passes->save($pass)) {
                return $this->json(['success' => false, 'message' => 'Could not start checkout.'], 500);
            }

            $base = rtrim((string)env('FRONTEND_URL', 'http://localhost:3000'), '/');
            $invoice = Xendit::createInvoice([
                'external_id' => $pass->external_id,
                'amount' => $plan['price'],
                'currency' => 'PHP',
                'payer_email' => $user->email,
                'description' => 'CareerPass — ' . $plan['name'] . ' · Civil Service Exam Reviewer',
                'success_redirect_url' => $base . '/checkout/success?ref=' . $pass->external_id,
                'failure_redirect_url' => $base . '/checkout/cancel',
                'invoice_duration' => 86400,
            ], $pass->external_id);

            $pass->xendit_invoice_id = (string)($invoice['id'] ?? '');
            $pass->invoice_url = (string)($invoice['invoice_url'] ?? '');
            if (!$passes->save($pass)) {
                // The invoice exists and is payable, so still send the buyer to
                // it. The webhook/reconcile find the invoice by external_id and
                // back-fill the id; only the duplicate-click reuse is lost.
                Log::error(sprintf(
                    '[checkout] pass %d (%s): could not store Xendit invoice %s',
                    $pass->pass_id,
                    $pass->external_id,
                    $pass->xendit_invoice_id,
                ));
            }

            return $this->json([
                'success' => true,
                'invoice_url' => $invoice['invoice_url'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('[checkout] ' . $e->getMessage());

            return $this->json(
                ['success' => false, 'message' => 'Something went wrong starting checkout. Please try again.'],
                500,
            );
        }
    }

    /**
     * POST /api/xendit/webhook — Xendit's invoice callback. Verified by the
     * `x-callback-token` header (no session, no AJAX gate — Xendit calls this
     * server-to-server).
     *
     * The callback only says *which* pass to look at. What happens to it is
     * decided by Xendit's own invoice record, fetched here and checked against
     * the pass (amount, currency, ids) — see App\Service\PassService.
     *
     * Status codes tell Xendit whether to retry:
     * - 2xx: handled (granted, expired, already done, unknown pass, or a
     *   mismatch that retrying can't fix — logged as an error);
     * - 503: Xendit's API unreachable, or it doesn't show the payment yet;
     * - 500: our side failed (database, data error) — nothing was half-written.
     * `bin/cake reconcile_payments` repairs anything retries didn't.
     */
    public function webhook(): Response
    {
        $this->request->allowMethod(['post']);

        $token = (string)env('XENDIT_WEBHOOK_TOKEN');
        if ($token === '' || !hash_equals($token, $this->request->getHeaderLine('x-callback-token'))) {
            return $this->json(['success' => false, 'message' => 'Invalid callback token'], 401);
        }

        $data = $this->request->getData();
        $externalId = (string)($data['external_id'] ?? '');
        $callbackStatus = strtoupper((string)($data['status'] ?? ''));

        try {
            /** @var \App\Model\Table\PassesTable $passes */
            $passes = $this->fetchTable('Passes');
            /** @var \App\Model\Entity\Pass|null $pass */
            $pass = $externalId !== ''
                ? $passes->find()->where(['external_id' => $externalId])->first()
                : null;

            if ($pass === null) {
                Log::warning("[xendit webhook] no pass for external_id '{$externalId}' — ignored");

                return $this->json(['success' => true, 'result' => 'unknown_pass']);
            }

            $result = (new PassService($passes))->reconcile($pass);
        } catch (XenditException $e) {
            Log::error("[xendit webhook] {$externalId}: could not verify with Xendit: " . $e->getMessage());

            return $this->json(['success' => false, 'message' => 'Could not verify the invoice; retry later.'], 503);
        } catch (Throwable $e) {
            Log::error("[xendit webhook] {$externalId}: " . $e->getMessage());

            return $this->json(['success' => false, 'message' => 'Could not process the callback; retry later.'], 500);
        }

        if ($result === PassService::INVOICE_NOT_FOUND) {
            Log::error("[xendit webhook] {$externalId}: Xendit has no invoice for this pass — not granted");
        }
        Log::info("[xendit webhook] {$externalId}: {$result} (callback {$callbackStatus})", ['scope' => 'payments']);

        // Xendit says it's paid, but its own API doesn't show that yet — for a
        // pending pass, or one already expired (payment right at expiry): retry.
        $unconfirmed = [PassService::STILL_PENDING, PassService::EXPIRED_UNPAID];
        if (in_array($result, $unconfirmed, true) && in_array($callbackStatus, PassService::PAID_STATUSES, true)) {
            return $this->json(['success' => false, 'message' => 'Payment not confirmed yet; retry later.'], 503);
        }

        return $this->json(['success' => true, 'result' => $result]);
    }

    /**
     * GET /api/purchases — the logged-in user's active passes (the post-checkout
     * poll on /checkout/success uses this).
     */
    public function myPasses(): Response
    {
        $userId = $this->activeUserId();
        if (!$userId) {
            return $this->json(['success' => false, 'message' => 'Not logged in'], 401);
        }

        return $this->json([
            'success' => true,
            'purchases' => $this->fetchTable('Passes')->activeForUser($userId),
        ])->withHeader('Cache-Control', 'no-store');
    }

    /**
     * GET /api/billing/history — every pass the user has paid for (for the
     * payment history + receipts in Settings).
     */
    public function history(): Response
    {
        $userId = $this->activeUserId();
        if (!$userId) {
            return $this->json(['success' => false, 'message' => 'Not logged in'], 401);
        }

        return $this->json([
            'success' => true,
            'purchases' => $this->fetchTable('Passes')->historyForUser($userId),
        ])->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body, int $status = 200): Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($body));
    }

    /**
     * Lightweight CSRF gate — see UsersController::requireAjaxHeader() for the
     * full reasoning (no CSRF middleware; SameSite=None cookies; the header
     * requirement reuses the CORS policy as the check).
     *
     * @return \Cake\Http\Response|null A 403 response if the header is missing, null to continue.
     */
    private function requireAjaxHeader(): ?Response
    {
        if ($this->request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest') {
            return $this->response
                ->withStatus(403)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Missing required request header']));
        }

        return null;
    }

    /**
     * The logged-in user's id, or null when there's no session — or when this
     * session has been superseded by a login on another device (mirrors
     * UsersController::activeUserId()).
     */
    private function activeUserId(): ?int
    {
        $session = $this->request->getSession();
        $user = $session->read('Auth.User');
        if (!$user) {
            return null;
        }

        $sessionToken = $session->read('Auth.token');
        $current = $this->fetchTable('Users')->find()
            ->select(['user_id', 'session_token'])
            ->where(['user_id' => $user->user_id])
            ->first();
        $dbToken = $current?->session_token;

        if ($sessionToken === null && $dbToken === null) {
            return (int)$user->user_id;
        }

        if (!$current || $sessionToken === null || $sessionToken !== $dbToken) {
            $session->delete('Auth.User');
            $session->delete('Auth.token');
            $session->renew();

            return null;
        }

        return (int)$user->user_id;
    }
}

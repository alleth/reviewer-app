<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController as BaseController;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\I18n\DateTime;

/**
 * Base for the `/api/*` resource controllers (Topics, Questions).
 *
 * - **Reads** require a valid session (the same single-device check as every
 *   other signed-in endpoint) whose user holds an active access pass — the
 *   question bank is paid content and must not be scrapeable.
 *   No usable session → 401 `{reason: signed_out | signed_in_elsewhere}`;
 *   signed in without a pass → 403 `{reason: no_active_pass}`.
 * - **Writes** (`add` / `edit` / `delete`) are disabled (403): content is
 *   loaded with `bin/cake import_questions`, there's no admin UI, and nothing
 *   on the frontend calls them.
 *
 * OPTIONS preflights never reach here — `CorsMiddleware` short-circuits them.
 */
class AppController extends BaseController
{
    /**
     * @var list<string>
     */
    protected array $writeActions = ['add', 'edit', 'delete'];

    /**
     * @param \Cake\Event\EventInterface $event The beforeFilter event.
     * @return \Cake\Http\Response|null A JSON 401/403 to halt dispatch, or null to continue.
     */
    public function beforeFilter(EventInterface $event): ?Response
    {
        parent::beforeFilter($event);

        if (in_array($this->request->getParam('action'), $this->writeActions, true)) {
            return $this->json(['success' => false, 'message' => 'This endpoint is not available.'], 403);
        }

        $userId = $this->activeUserId();
        if ($userId === null) {
            return $this->json([
                'success' => false,
                'reason' => $this->sessionDenialReason,
                'message' => $this->sessionDenialReason === 'signed_in_elsewhere'
                    ? 'You signed in on another device.'
                    : 'Your session is no longer valid. Please sign in again.',
            ], 401);
        }

        if (!$this->hasActivePass($userId)) {
            return $this->json([
                'success' => false,
                'reason' => 'no_active_pass',
                'message' => 'An active pass is required to access reviewer content.',
            ], 403);
        }

        return null;
    }

    /**
     * Whether the user has at least one paid, unexpired pass. Takes the id from
     * activeUserId(), never the session copy, so a stale session can't use it.
     */
    private function hasActivePass(int $userId): bool
    {
        return $this->fetchTable('Passes')->find()
            ->where([
                'user_id' => $userId,
                'status' => 'paid',
                'expires_at >' => new DateTime(),
            ])
            ->count() > 0;
    }
}

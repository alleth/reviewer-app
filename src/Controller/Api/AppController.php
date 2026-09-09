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
 * - **Reads** require the caller to be logged in with an active access pass —
 *   the question bank is paid content and must not be scrapeable.
 * - **Writes** (`add` / `edit` / `delete`) are disabled: content is loaded with
 *   `bin/cake import_questions`, there's no admin UI, and nothing on the
 *   frontend calls them.
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
     * @return \Cake\Http\Response|null A JSON 403 to halt dispatch, or null to continue.
     */
    public function beforeFilter(EventInterface $event): ?Response
    {
        parent::beforeFilter($event);

        if (in_array($this->request->getParam('action'), $this->writeActions, true)) {
            return $this->deny('This endpoint is not available.');
        }

        if (!$this->hasActivePass()) {
            return $this->deny('An active pass is required to access reviewer content.');
        }

        return null;
    }

    /**
     * @param string $message Message to return.
     * @return \Cake\Http\Response
     */
    private function deny(string $message): Response
    {
        return $this->response
            ->withStatus(403)
            ->withType('application/json')
            ->withStringBody((string)json_encode(['success' => false, 'message' => $message]));
    }

    /**
     * Whether the session user has at least one paid, unexpired pass.
     */
    private function hasActivePass(): bool
    {
        $user = $this->request->getSession()->read('Auth.User');
        if (!$user) {
            return false;
        }

        return $this->fetchTable('Passes')->find()
            ->where([
                'user_id' => $user->user_id,
                'status' => 'paid',
                'expires_at >' => new DateTime(),
            ])
            ->count() > 0;
    }
}

<?php
declare(strict_types=1);

namespace App\Test\TestSuite;

use App\Model\Entity\User;
use Cake\ORM\TableRegistry;

/**
 * Shared helpers for integration tests against the JSON API.
 *
 * Use alongside Cake\TestSuite\IntegrationTestTrait. Covers the app's manual
 * auth: the X-Requested-With CSRF gate, and a single-device session
 * (`Auth.User` + `Auth.token`, which must match `users.session_token`).
 */
trait ApiTestTrait
{
    /**
     * Env values overridden by setEnv(), restored in restoreEnv().
     *
     * @var array<string, mixed>
     */
    private array $envBackup = [];

    /**
     * Sends the next request the way the SPA's `api` axios instance does.
     *
     * @return void
     */
    protected function asAjax(): void
    {
        $this->configRequest([
            'headers' => ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
        ]);
    }

    /**
     * Puts the next request in a valid logged-in session for $userId, exactly as
     * UsersController::startSession() would: rotates the user's session_token
     * in the database and stores the same token in the session.
     *
     * @param int $userId User id (must exist in the Users fixture).
     * @return \App\Model\Entity\User
     */
    protected function loginAs(int $userId): User
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        /** @var \App\Model\Entity\User $user */
        $user = $users->get($userId);
        $token = bin2hex(random_bytes(20));
        $user->session_token = $token;
        $users->saveOrFail($user);

        $this->session(['Auth' => ['User' => $user, 'token' => $token]]);

        return $user;
    }

    /**
     * The decoded JSON body of the last response.
     *
     * @return array<string, mixed>
     */
    protected function responseJson(): array
    {
        return (array)json_decode((string)$this->_response->getBody(), true);
    }

    /**
     * Overrides an env() value for the current test. CakePHP's env() reads
     * $_SERVER first, so that is what gets set; restoreEnv() puts it back.
     *
     * @param string $name Variable name.
     * @param string|null $value New value, or null to unset.
     * @return void
     */
    protected function setEnv(string $name, ?string $value): void
    {
        if (!array_key_exists($name, $this->envBackup)) {
            $this->envBackup[$name] = $_SERVER[$name] ?? null;
        }
        if ($value === null) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $value;
        }
    }

    /**
     * Restores every value changed by setEnv(). Call from tearDown().
     *
     * @return void
     */
    protected function restoreEnv(): void
    {
        foreach ($this->envBackup as $name => $value) {
            if ($value === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $value;
            }
        }
        $this->envBackup = [];
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * User Entity
 *
 * @property int $user_id
 * @property string|null $fname
 * @property string|null $lname
 * @property string|null $email
 * @property string|null $user_name
 * @property string|null $user_pass
 * @property string|null $google_id
 * @property string|null $session_token
 * @property \Cake\I18n\DateTime|null $email_verified_at
 * @property bool $password_set
 */
class User extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Profile fields only. `user_pass`, `google_id`, `session_token` and
     * `email_verified_at` decide who can sign in to the account, so they are
     * never taken from request data — UsersController assigns each one
     * explicitly. (`register` used to accept a `google_id` from the request,
     * which let anyone pre-link an account to their own Google login.)
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'fname' => true,
        'lname' => true,
        'email' => true,
        'user_name' => true,
    ];

    /**
     * Never expose the password hash or the single-device session token in API
     * responses (the hash used to be sent to the client and stored in
     * localStorage).
     *
     * @var list<string>
     */
    protected array $_hidden = ['user_pass', 'session_token'];

    /**
     * Virtual fields serialized alongside the real ones.
     *
     * @var list<string>
     */
    protected array $_virtual = ['password_set'];

    /**
     * Whether this account has a usable password (Google-only accounts don't
     * until they finish setup). Lets the frontend show account-completion state
     * without ever seeing the hash.
     */
    protected function _getPasswordSet(): bool
    {
        return $this->user_pass !== null && $this->user_pass !== '';
    }

    /**
     * Whether the account's email address has been proven (an emailed code, a
     * password reset, or a Google login that Google says is verified). An
     * unverified account never gets a session.
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }
}

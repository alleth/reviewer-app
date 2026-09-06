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
 * @property bool $password_set
 */
class User extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'fname' => true,
        'lname' => true,
        'email' => true,
        'user_name' => true,
        'user_pass' => true,
        'google_id' => true,
    ];

    /**
     * Never expose the password hash in API responses (it used to be sent to
     * the client and stored in localStorage).
     *
     * @var list<string>
     */
    protected array $_hidden = ['user_pass'];

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
}

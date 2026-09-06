<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * AuthCode Entity
 *
 * @property int $auth_code_id
 * @property int $user_id
 * @property string $purpose
 * @property string $code_hash
 * @property \Cake\I18n\DateTime $expires
 * @property int $attempts
 * @property \Cake\I18n\DateTime|null $created
 */
class AuthCode extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'purpose' => true,
        'code_hash' => true,
        'expires' => true,
        'attempts' => true,
        'created' => true,
    ];

    protected array $_hidden = ['code_hash'];
}

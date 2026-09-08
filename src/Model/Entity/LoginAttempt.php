<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * LoginAttempt Entity
 *
 * @property int $login_attempt_id
 * @property string $identifier
 * @property \Cake\I18n\DateTime|null $created
 */
class LoginAttempt extends Entity
{
    protected array $_accessible = [
        'identifier' => true,
        'created' => true,
    ];
}

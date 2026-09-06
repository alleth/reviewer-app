<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * LoginEvent Entity
 *
 * @property int $login_event_id
 * @property int $user_id
 * @property string $device_hash
 * @property \Cake\I18n\DateTime|null $created
 */
class LoginEvent extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'device_hash' => true,
        'created' => true,
    ];
}

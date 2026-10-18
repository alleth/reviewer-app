<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * One counted action for App\Service\RateLimiter.
 *
 * @property int $rate_limit_id
 * @property string $bucket
 * @property string $key_hash
 * @property \Cake\I18n\DateTime $created
 */
class RateLimit extends Entity
{
    /**
     * Written only by RateLimiter, never from request data.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'bucket' => true,
        'key_hash' => true,
        'created' => true,
    ];
}

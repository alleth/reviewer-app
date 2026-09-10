<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Pass Entity
 *
 * @property int $pass_id
 * @property int $user_id
 * @property string $reviewer
 * @property string $plan_id
 * @property string $amount
 * @property string $currency
 * @property string $external_id
 * @property string|null $xendit_invoice_id
 * @property string|null $invoice_url
 * @property string $status
 * @property \Cake\I18n\DateTime|null $paid_at
 * @property \Cake\I18n\DateTime|null $expires_at
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class Pass extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'reviewer' => true,
        'plan_id' => true,
        'amount' => true,
        'currency' => true,
        'external_id' => true,
        'xendit_invoice_id' => true,
        'invoice_url' => true,
        'status' => true,
        'paid_at' => true,
        'expires_at' => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\Pass;
use App\Payment\Plans;
use Cake\I18n\DateTime;
use Cake\ORM\Table;

/**
 * Passes Model — access passes bought through Xendit. See the CreatePasses
 * migration for the row lifecycle. `belongsTo` Users.
 *
 * @method \App\Model\Entity\Pass newEmptyEntity()
 * @method \App\Model\Entity\Pass newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Pass get(mixed $primaryKey, array|string $finder = 'all', mixed ...$args)
 * @method \App\Model\Entity\Pass|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class PassesTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('passes');
        $this->setDisplayField('plan_id');
        $this->setPrimaryKey('pass_id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }

    /**
     * The user's currently-usable passes, in the shape the frontend expects
     * (webroot/react-frontend/src/reviewers.js → getPurchases): one entry per
     * active `paid` pass whose access window hasn't closed.
     *
     * @param int $userId User id.
     * @return array<int, array{id: int, reviewer: string, plan: string, planId: string, purchasedAt: ?string, expiresAt: ?string}>
     */
    public function activeForUser(int $userId): array
    {
        $rows = $this->find()
            ->where([
                'user_id' => $userId,
                'status' => 'paid',
                'expires_at >' => new DateTime(),
            ])
            ->orderBy(['expires_at' => 'DESC'])
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $plan = Plans::get((string)$row->plan_id);
            $out[] = [
                'id' => (int)$row->pass_id,
                'reviewer' => (string)$row->reviewer,
                'plan' => $plan['name'] ?? (string)$row->plan_id,
                'planId' => (string)$row->plan_id,
                'purchasedAt' => $row->paid_at?->toIso8601String(),
                'expiresAt' => $row->expires_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * The one active `paid` pass for ($userId, $reviewer), or null. Used by the
     * webhook to extend an existing window instead of stacking a second card.
     *
     * @param int $userId User id.
     * @param string $reviewer Reviewer id.
     * @return \App\Model\Entity\Pass|null
     */
    public function activeForReviewer(int $userId, string $reviewer): ?Pass
    {
        /** @var \App\Model\Entity\Pass|null $pass */
        $pass = $this->find()
            ->where([
                'user_id' => $userId,
                'reviewer' => $reviewer,
                'status' => 'paid',
                'expires_at >' => new DateTime(),
            ])
            ->orderBy(['expires_at' => 'DESC'])
            ->first();

        return $pass;
    }
}

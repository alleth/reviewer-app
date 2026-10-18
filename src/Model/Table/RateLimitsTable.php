<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * RateLimits Model — one row per counted action in a named bucket, keyed by an
 * HMAC (see App\Service\RateLimiter). No associations: a key can be a client
 * IP, or name an account that does not exist.
 *
 * @method \App\Model\Entity\RateLimit newEmptyEntity()
 * @method \App\Model\Entity\RateLimit newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\RateLimit|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class RateLimitsTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rate_limits');
        $this->setDisplayField('bucket');
        $this->setPrimaryKey('rate_limit_id');
    }
}

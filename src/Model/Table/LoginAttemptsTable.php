<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * LoginAttempts Model — one row per failed login, keyed by the submitted
 * identifier (lowercased username/email). Backs the password brute-force
 * throttle in UsersController::login(). No Users association: an attempt can
 * name an account that does not exist.
 *
 * @method \App\Model\Entity\LoginAttempt newEmptyEntity()
 * @method \App\Model\Entity\LoginAttempt newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\LoginAttempt|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class LoginAttemptsTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('login_attempts');
        $this->setDisplayField('identifier');
        $this->setPrimaryKey('login_attempt_id');
    }
}

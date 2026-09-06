<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * LoginEvents Model — one row per completed login, used by the rapid
 * device-switch guard in UsersController::loginChallenge().
 *
 * @method \App\Model\Entity\LoginEvent newEmptyEntity()
 * @method \App\Model\Entity\LoginEvent newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\LoginEvent|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class LoginEventsTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('login_events');
        $this->setDisplayField('device_hash');
        $this->setPrimaryKey('login_event_id');

        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }
}

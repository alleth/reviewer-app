<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * AuthCodes Model — one-time emailed codes (password reset, verification, login
 * challenge). See UsersController for the issue/verify logic.
 *
 * @method \App\Model\Entity\AuthCode newEmptyEntity()
 * @method \App\Model\Entity\AuthCode newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\AuthCode get(mixed $primaryKey, array|string $finder = 'all', ...)
 * @method \App\Model\Entity\AuthCode|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class AuthCodesTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('auth_codes');
        $this->setDisplayField('purpose');
        $this->setPrimaryKey('auth_code_id');

        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }
}

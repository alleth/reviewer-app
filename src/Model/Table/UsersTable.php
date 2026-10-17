<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\User;
use Cake\Database\Expression\FunctionExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Closure;

/**
 * Users Model
 *
 * @method \App\Model\Entity\User newEmptyEntity()
 * @method \App\Model\Entity\User newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\User> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\User get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\User findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\User patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\User> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\User|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\User saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\User>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\User>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\User>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\User> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\User>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\User>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\User>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\User> deleteManyOrFail(iterable $entities, array $options = [])
 */
class UsersTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('user_id');
        $this->setPrimaryKey('user_id');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        // Max lengths mirror the real `users` columns (confirmed against Railway
        // production): fname/lname/user_name VARCHAR(50), email VARCHAR(100).
        // Names may be empty — Google accounts don't always carry a given or
        // family name — but email and user_name identify the account and may not.
        $validator
            ->scalar('fname')
            ->maxLength('fname', 50, 'First name must be 50 characters or fewer.')
            ->allowEmptyString('fname');

        $validator
            ->scalar('lname')
            ->maxLength('lname', 50, 'Last name must be 50 characters or fewer.')
            ->allowEmptyString('lname');

        $validator
            ->scalar('email')
            ->requirePresence('email', 'create', 'Email is required.')
            ->notEmptyString('email', 'Email is required.')
            ->maxLength('email', 100, 'Email must be 100 characters or fewer.')
            ->email('email', false, 'Enter a valid email address.');

        $validator
            ->scalar('user_name')
            ->requirePresence('user_name', 'create', 'Username is required.')
            ->notEmptyString('user_name', 'Username is required.')
            ->maxLength('user_name', 50, 'Username must be 50 characters or fewer.');

        // Stores a password_hash() digest, not the plaintext. bcrypt is 60 chars
        // today, but PASSWORD_DEFAULT can change to a longer algorithm — keep this
        // at 255 (and the DB column VARCHAR(255)) so a future hash is never
        // silently truncated. Plaintext length is checked in UsersController.
        $validator
            ->scalar('user_pass')
            ->maxLength('user_pass', 255)
            ->allowEmptyString('user_pass');

        // Set only by UsersController (it isn't mass-assignable); NULL = unverified.
        $validator
            ->dateTime('email_verified_at')
            ->allowEmptyDateTime('email_verified_at');

        return $validator;
    }

    /**
     * Application integrity rules: email and username are each unique. Email
     * uniqueness ignores letter case (see emailTaken()).
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(
            function (EntityInterface $entity): bool {
                $email = $entity->get('email');
                if (!is_string($email) || (!$entity->isNew() && !$entity->isDirty('email'))) {
                    return true;
                }
                $userId = $entity->isNew() ? null : (int)$entity->get('user_id');

                return !$this->emailTaken($email, $userId);
            },
            'uniqueEmail',
            ['errorField' => 'email', 'message' => 'This email is already registered.'],
        );
        $rules->add($rules->isUnique(['user_name'], 'This username is already taken.'));

        return $rules;
    }

    /**
     * The form every email comparison uses: trimmed and lowercased. Addresses
     * are stored as the user typed them, but two that differ only in letter
     * case are the same address everywhere — production's MySQL collation
     * already compares them that way, and SQLite (the test database) doesn't,
     * so the app never relies on the database for it.
     *
     * Accents are not folded here, but production's collation
     * (utf8mb4_0900_ai_ci) also treats "é" and "e" as equal, so two addresses
     * differing only by an accent still hit the `users_email` unique index;
     * that surfaces as "This email is already registered." (accepted, H3 I4).
     */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * The account with this email address, ignoring letter case, or null.
     */
    public function findByEmailAddress(string $email): ?User
    {
        $normalized = self::normalizeEmail($email);
        if ($normalized === '') {
            return null;
        }

        /** @var \App\Model\Entity\User|null $user */
        $user = $this->find()->where($this->emailIs($normalized))->first();

        return $user;
    }

    /**
     * Whether another account (not $exceptUserId) already has this email
     * address, ignoring letter case.
     */
    public function emailTaken(string $email, ?int $exceptUserId = null): bool
    {
        $normalized = self::normalizeEmail($email);
        if ($normalized === '') {
            return false;
        }

        $query = $this->find()->where($this->emailIs($normalized));
        if ($exceptUserId !== null) {
            $query->where(['user_id !=' => $exceptUserId]);
        }

        return $query->count() > 0;
    }

    /**
     * A condition matching rows whose email, lowercased, equals $normalized.
     */
    public function emailIs(string $normalized): Closure
    {
        return fn(QueryExpression $exp): QueryExpression => $exp->eq(
            new FunctionExpression('LOWER', ['email' => 'identifier']),
            $normalized,
        );
    }
}

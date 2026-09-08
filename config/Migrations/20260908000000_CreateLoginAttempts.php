<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Failed-login records for the password brute-force throttle in
 * UsersController::login(). One row per failed attempt, keyed by the submitted
 * identifier (lowercased username/email) — deliberately not a users FK, since a
 * guess can name an account that does not exist. Rows are cleared on a
 * successful login for that identifier and pruned after a day.
 *
 * NOTE: docker-start.sh does not run migrations, so on Railway apply the
 * equivalent SQL by hand (see CLAUDE.md).
 */
class CreateLoginAttempts extends AbstractMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('login_attempts', ['id' => false, 'primary_key' => ['login_attempt_id']])
            ->addColumn('login_attempt_id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('identifier', 'string', ['limit' => 255])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['identifier', 'created'])
            ->create();
    }
}

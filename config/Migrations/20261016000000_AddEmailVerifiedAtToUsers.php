<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * B3-V: email verification. NULL = the address hasn't been proven yet, and
 * such an account never gets a session (see UsersController). Every account
 * that exists when the column is added is marked verified (there were no real
 * users yet, and existing test accounts shouldn't be pushed through it).
 *
 * NOTE: docker-start.sh does not run migrations. On Railway, apply the
 * equivalent SQL by hand *before* deploying the code that reads the column —
 * the new code treats a missing/NULL value as unverified — then record it:
 *
 *   ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER session_token;
 *   UPDATE users SET email_verified_at = NOW() WHERE email_verified_at IS NULL;
 *   php bin/cake.php migrations mark_migrated --target 20261016000000 --only
 */
class AddEmailVerifiedAtToUsers extends AbstractMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('users')
            ->addColumn('email_verified_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'session_token',
            ])
            ->update();

        $this->execute('UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE email_verified_at IS NULL');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('users')
            ->removeColumn('email_verified_at')
            ->update();
    }
}

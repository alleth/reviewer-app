<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * One-time codes emailed to users for password reset, account verification and
 * login challenges. Short-lived; a row is deleted once used or expired.
 *
 * NOTE: docker-start.sh does not run migrations, so on Railway apply the
 * equivalent SQL by hand (see CLAUDE.md).
 */
class CreateAuthCodes extends AbstractMigration
{
    public function change(): void
    {
        $this->table('auth_codes', ['id' => false, 'primary_key' => ['auth_code_id']])
            ->addColumn('auth_code_id', 'integer', ['identity' => true, 'signed' => false])
            // Signed (not unsigned) to match users.user_id, which is `int(11)` signed.
            ->addColumn('user_id', 'integer', [])
            ->addColumn('purpose', 'string', ['limit' => 30])
            ->addColumn('code_hash', 'string', ['limit' => 255])
            ->addColumn('expires', 'datetime', [])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['user_id', 'purpose'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('login_events', ['id' => false, 'primary_key' => ['login_event_id']])
            ->addColumn('login_event_id', 'integer', ['identity' => true, 'signed' => false])
            // Signed (not unsigned) to match users.user_id, which is `int(11)` signed.
            ->addColumn('user_id', 'integer', [])
            ->addColumn('device_hash', 'string', ['limit' => 64])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['user_id', 'created'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * `users` predates migrations (see CLAUDE.md) and already exists on every real
 * database (local + Railway) — this migration exists so the SQLite *test*
 * schema (built from scratch, purely from migrations, in tests/bootstrap.php)
 * has something for the `users`-FK migrations (auth_codes, passes,
 * topic_reviews, …) to reference. It must NEVER actually run against a real
 * database (the table already exists there) — mark it applied instead:
 *
 *   bin/cake migrations mark_migrated --target 20260528000000 --only
 *
 * Mirrors production's real table as of 2026-09-10 — confirmed directly
 * against Railway, not assumed from a local copy (local had drifted: signed
 * `user_id` instead of unsigned, nullable/shorter fname/lname/email/user_name
 * with no DB-level uniqueness on email/user_name, narrower google_id — local
 * was fixed to match production rather than the other way around). No
 * `created`/`modified`: UsersTable doesn't use the Timestamp behavior.
 */
class CreateUsers extends AbstractMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('users', ['id' => false, 'primary_key' => ['user_id']])
            ->addColumn('user_id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('fname', 'string', ['limit' => 50])
            ->addColumn('lname', 'string', ['limit' => 50])
            ->addColumn('email', 'string', ['limit' => 100])
            ->addColumn('user_name', 'string', ['limit' => 50])
            ->addColumn('user_pass', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('google_id', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('session_token', 'string', ['limit' => 64, 'null' => true, 'default' => null])
            ->addIndex(['user_name'], ['unique' => true, 'name' => 'users_user_name'])
            ->addIndex(['email'], ['unique' => true, 'name' => 'users_email'])
            ->addIndex(['google_id'], ['unique' => true, 'name' => 'users_google_id'])
            ->create();
    }
}

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
 * Mirrors the real table exactly as of 2026-09-10 (including that only
 * `google_id` has a DB-level unique constraint — `email`/`user_name`
 * uniqueness is app-level only, via UsersTable's build rules). No
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
            ->addColumn('user_id', 'integer', ['identity' => true])
            ->addColumn('fname', 'string', ['limit' => 45, 'null' => true, 'default' => null])
            ->addColumn('lname', 'string', ['limit' => 45, 'null' => true, 'default' => null])
            ->addColumn('email', 'string', ['limit' => 35, 'null' => true, 'default' => null])
            ->addColumn('user_name', 'string', ['limit' => 35, 'null' => true, 'default' => null])
            ->addColumn('user_pass', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('google_id', 'string', ['limit' => 64, 'null' => true, 'default' => null])
            ->addColumn('session_token', 'string', ['limit' => 64, 'null' => true, 'default' => null])
            ->addIndex(['google_id'], ['unique' => true, 'name' => 'uniq_google_id'])
            ->create();
    }
}

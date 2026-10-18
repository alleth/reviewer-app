<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Events for App\Service\RateLimiter (H4): one row per counted action — a
 * failed sign-in, a wrong code guess, a forgot-password request — in a named
 * bucket. Keys are HMACs (an account id, a typed identifier, or a client IP),
 * never the raw value, and no column references users: a key can name an
 * account that does not exist. Rows older than 7 days are pruned on write.
 * Replaces login_attempts, which is left in place until a later cleanup.
 *
 * NOTE: docker-start.sh does not run migrations, so on Railway apply the
 * equivalent SQL by hand before the code that uses it deploys (see CLAUDE.md).
 */
class CreateRateLimits extends AbstractMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('rate_limits', ['id' => false, 'primary_key' => ['rate_limit_id']])
            ->addColumn('rate_limit_id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('bucket', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('key_hash', 'char', ['limit' => 64, 'null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['bucket', 'key_hash', 'created'], ['name' => 'rate_limits_lookup'])
            ->addIndex(['created'], ['name' => 'rate_limits_created'])
            ->create();
    }
}

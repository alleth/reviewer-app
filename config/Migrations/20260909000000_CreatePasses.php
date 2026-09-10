<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Access passes — one row per purchase attempt. A row starts `pending` when
 * checkout creates the Xendit invoice, becomes `paid` (with `expires_at`) on the
 * paid webhook, `expired` if the invoice lapses unpaid, and `superseded` when a
 * later pass for the same reviewer extends it. Backs PaymentsController and the
 * `purchases` array on the serialized user.
 *
 * NOTE: docker-start.sh does not run migrations, so on Railway apply the
 * equivalent SQL by hand (see CLAUDE.md).
 */
class CreatePasses extends AbstractMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('passes', ['id' => false, 'primary_key' => ['pass_id']])
            ->addColumn('pass_id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('reviewer', 'string', ['limit' => 50])
            ->addColumn('plan_id', 'string', ['limit' => 20])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'PHP'])
            ->addColumn('external_id', 'string', ['limit' => 64])
            ->addColumn('xendit_invoice_id', 'string', ['limit' => 64, 'null' => true, 'default' => null])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
            ->addColumn('paid_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('expires_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['external_id'], ['unique' => true])
            ->addIndex(['user_id', 'status', 'expires_at'])
            ->addForeignKey('user_id', 'users', 'user_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}

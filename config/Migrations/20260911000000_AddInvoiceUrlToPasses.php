<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Persists the Xendit hosted-invoice URL on the pass row itself, so
 * PaymentsController::checkout() can reuse a very-recent still-pending
 * invoice (same user/reviewer/plan, created in the last few minutes)
 * instead of creating a duplicate one on every retry/double-click/extra
 * tab — see CLAUDE.md "Payments" for the full reasoning.
 *
 * NOTE: docker-start.sh does not run migrations, so on Railway apply the
 * equivalent SQL by hand (see CLAUDE.md).
 */
class AddInvoiceUrlToPasses extends AbstractMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('passes')
            ->addColumn('invoice_url', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'after' => 'xendit_invoice_id',
            ])
            ->update();
    }
}

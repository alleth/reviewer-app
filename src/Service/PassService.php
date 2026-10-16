<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Pass;
use App\Model\Table\PassesTable;
use App\Payment\Plans;
use App\Payment\Xendit;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use LogicException;
use RuntimeException;

/**
 * Turns a pending access pass into a paid (or expired) one, based on
 * **Xendit's own record of the invoice**, never on what a callback claims.
 *
 * Used by the webhook (PaymentsController::webhook()) and by the
 * `reconcile_payments` command, so both follow the same rules:
 *
 * - the invoice is fetched from Xendit and must match the pass (external_id,
 *   invoice id, amount, currency) before anything is granted;
 * - granting happens in one transaction: the pending → paid claim and the
 *   supersede of the previous pass both commit, or neither does;
 * - the claim is a conditional update (`WHERE status = 'pending'`), so two
 *   callbacks racing for the same pass grant it once;
 * - every write is checked; a failed write throws and rolls back.
 *
 * An `expired` pass is re-checked too: a payment made right at expiry can be
 * confirmed after the EXPIRED notification was already applied. If Xendit
 * then shows the invoice paid (same checks as above), the pass is granted
 * late (`WHERE status = 'expired'`) instead of the money going unhonored.
 *
 * Xendit being unreachable surfaces as App\Payment\XenditException, which
 * callers treat as "try again later".
 */
class PassService
{
    public const GRANTED = 'granted';
    public const EXPIRED = 'expired';
    public const FAILED = 'failed';
    public const ALREADY_HANDLED = 'already_handled';
    public const STILL_PENDING = 'still_pending';
    public const MISMATCH = 'mismatch';
    public const INVOICE_NOT_FOUND = 'invoice_not_found';
    /** An expired pass whose invoice Xendit now shows paid: granted after all. */
    public const LATE_GRANTED = 'granted_late';
    /** An expired pass whose invoice Xendit still doesn't show paid. */
    public const EXPIRED_UNPAID = 'expired_unpaid';

    /** Xendit invoice statuses that mean "money received". */
    public const PAID_STATUSES = ['PAID', 'SETTLED'];

    /**
     * @param \App\Model\Table\PassesTable $passes Passes table.
     */
    public function __construct(private PassesTable $passes)
    {
    }

    /**
     * Looks the pass's invoice up at Xendit and applies it.
     *
     * @param \App\Model\Entity\Pass $pass The pass.
     * @return string One of the result constants.
     * @throws \App\Payment\XenditException When Xendit can't be reached.
     */
    public function reconcile(Pass $pass): string
    {
        if ($pass->status !== 'pending' && $pass->status !== 'expired') {
            // paid / superseded / failed: nothing Xendit can change.
            return self::ALREADY_HANDLED;
        }

        $invoice = $this->fetchInvoice($pass);
        if ($invoice === null) {
            return $pass->status === 'expired' ? self::ALREADY_HANDLED : self::INVOICE_NOT_FOUND;
        }

        return $this->applyInvoice($pass, $invoice);
    }

    /**
     * Xendit's record of the pass's invoice: by invoice id when we stored
     * one, else by our external_id.
     *
     * @param \App\Model\Entity\Pass $pass The pass.
     * @return array<string, mixed>|null
     * @throws \App\Payment\XenditException When Xendit can't be reached.
     */
    public function fetchInvoice(Pass $pass): ?array
    {
        $invoiceId = (string)$pass->xendit_invoice_id;

        return $invoiceId !== ''
            ? Xendit::getInvoice($invoiceId)
            : Xendit::findInvoiceByExternalId((string)$pass->external_id);
    }

    /**
     * What applyInvoice() would do, without writing anything. Decides on
     * Xendit's invoice alone; for an `expired` pass only GRANTED leads to a
     * change (see applyInvoice()).
     *
     * @param \App\Model\Entity\Pass $pass The pass.
     * @param array<string, mixed> $invoice Xendit's invoice record.
     * @return string GRANTED, EXPIRED, STILL_PENDING or MISMATCH.
     */
    public function decide(Pass $pass, array $invoice): string
    {
        if ($this->mismatchReason($pass, $invoice) !== null) {
            return self::MISMATCH;
        }

        $status = strtoupper((string)($invoice['status'] ?? ''));
        if (in_array($status, self::PAID_STATUSES, true)) {
            return self::GRANTED;
        }
        if ($status === 'EXPIRED') {
            return self::EXPIRED;
        }

        return self::STILL_PENDING;
    }

    /**
     * Applies Xendit's invoice record to the pass.
     *
     * @param \App\Model\Entity\Pass $pass The pass.
     * @param array<string, mixed> $invoice Xendit's invoice record.
     * @return string One of the result constants.
     */
    public function applyInvoice(Pass $pass, array $invoice): string
    {
        $reason = $this->mismatchReason($pass, $invoice);
        if ($reason !== null) {
            Log::error(sprintf(
                '[payments] pass %d (%s): Xendit invoice does not match, not granting: %s',
                $pass->pass_id,
                $pass->external_id,
                $reason,
            ));

            return self::MISMATCH;
        }

        $decision = $this->decide($pass, $invoice);

        if ($pass->status === 'expired') {
            // Only a confirmed payment changes an expired pass; it is never
            // closed again.
            return $decision === self::GRANTED
                ? $this->grant($pass, $invoice, 'expired')
                : self::EXPIRED_UNPAID;
        }

        return match ($decision) {
            self::GRANTED => $this->grant($pass, $invoice),
            self::EXPIRED => $this->closePending($pass, 'expired', $invoice) ? self::EXPIRED : self::ALREADY_HANDLED,
            default => self::STILL_PENDING,
        };
    }

    /**
     * Marks a pending pass failed (no invoice ever reached Xendit).
     *
     * @param \App\Model\Entity\Pass $pass The pass.
     * @return string FAILED, or ALREADY_HANDLED if it was no longer pending.
     */
    public function markFailed(Pass $pass): string
    {
        return $this->closePending($pass, 'failed', []) ? self::FAILED : self::ALREADY_HANDLED;
    }

    /**
     * Grants the pass: $from (pending, or expired for a late payment) → paid,
     * with an expiry stacked on any active pass for the same (user, reviewer),
     * which becomes `superseded`.
     *
     * @param \App\Model\Entity\Pass $pass The pass.
     * @param array<string, mixed> $invoice Xendit's invoice record.
     * @param string $from The status the pass must still have: `pending` or `expired`.
     * @return string GRANTED (LATE_GRANTED when $from is `expired`) or ALREADY_HANDLED.
     */
    private function grant(Pass $pass, array $invoice, string $from = 'pending'): string
    {
        $plan = Plans::get((string)$pass->plan_id);
        if ($plan === null) {
            // Our data is wrong, not Xendit's: fail loudly so it is retried
            // (and reconciled) once fixed, instead of acknowledging a payment
            // we did not honor.
            throw new LogicException("Pass {$pass->pass_id} has unknown plan '{$pass->plan_id}'.");
        }

        $connection = $this->passes->getConnection();
        $result = $connection->transactional(function () use ($pass, $plan, $invoice, $from): string {
            $existing = $this->passes->activeForReviewer((int)$pass->user_id, (string)$pass->reviewer);
            $now = new DateTime();
            $base = $existing?->expires_at !== null && $existing->expires_at->isFuture()
                ? $existing->expires_at
                : $now;

            $claimed = $this->passes->updateAll(
                [
                    'status' => 'paid',
                    'paid_at' => $now,
                    'expires_at' => $base->addDays($plan['days']),
                    'modified' => $now,
                ] + $this->invoiceIdPatch($pass, $invoice),
                ['pass_id' => $pass->pass_id, 'status' => $from],
            );
            if ($claimed === 0) {
                // Another callback (or the reconcile command) got here first.
                return self::ALREADY_HANDLED;
            }

            if ($existing !== null && $existing->pass_id !== $pass->pass_id) {
                $superseded = $this->passes->updateAll(
                    ['status' => 'superseded', 'modified' => $now],
                    ['pass_id' => $existing->pass_id, 'status' => 'paid'],
                );
                if ($superseded !== 1) {
                    throw new RuntimeException(
                        "Could not supersede pass {$existing->pass_id} while granting pass {$pass->pass_id}.",
                    );
                }
            }

            return $from === 'expired' ? self::LATE_GRANTED : self::GRANTED;
        });

        if ($result === self::LATE_GRANTED) {
            // Error level so it always surfaces: money arrived after we had
            // given up on the invoice, which is worth a human look.
            Log::error(sprintf(
                '[payments] pass %d (%s): payment confirmed after the pass was marked expired; granted late',
                $pass->pass_id,
                $pass->external_id,
            ));
        }

        return $result;
    }

    /**
     * pending → $status, only if still pending.
     *
     * @param \App\Model\Entity\Pass $pass The pass.
     * @param string $status `expired` or `failed`.
     * @param array<string, mixed> $invoice Xendit's invoice record, if any.
     * @return bool Whether this call changed it.
     */
    private function closePending(Pass $pass, string $status, array $invoice): bool
    {
        $changed = $this->passes->updateAll(
            ['status' => $status, 'modified' => new DateTime()] + $this->invoiceIdPatch($pass, $invoice),
            ['pass_id' => $pass->pass_id, 'status' => 'pending'],
        );

        return $changed === 1;
    }

    /**
     * Back-fills the Xendit invoice id when checkout never stored it.
     *
     * @param \App\Model\Entity\Pass $pass The pass.
     * @param array<string, mixed> $invoice Xendit's invoice record.
     * @return array<string, string>
     */
    private function invoiceIdPatch(Pass $pass, array $invoice): array
    {
        $invoiceId = (string)($invoice['id'] ?? '');
        if ((string)$pass->xendit_invoice_id === '' && $invoiceId !== '') {
            return ['xendit_invoice_id' => $invoiceId];
        }

        return [];
    }

    /**
     * Why Xendit's invoice can't be the one for this pass, or null if it matches.
     *
     * @param \App\Model\Entity\Pass $pass The pass.
     * @param array<string, mixed> $invoice Xendit's invoice record.
     * @return string|null
     */
    private function mismatchReason(Pass $pass, array $invoice): ?string
    {
        if ((string)($invoice['external_id'] ?? '') !== (string)$pass->external_id) {
            return sprintf('external_id %s != %s', $invoice['external_id'] ?? '(none)', $pass->external_id);
        }

        $storedId = (string)$pass->xendit_invoice_id;
        if ($storedId !== '' && (string)($invoice['id'] ?? '') !== $storedId) {
            return sprintf('invoice id %s != %s', $invoice['id'] ?? '(none)', $storedId);
        }

        if (strtoupper((string)($invoice['currency'] ?? '')) !== strtoupper((string)$pass->currency)) {
            return sprintf('currency %s != %s', $invoice['currency'] ?? '(none)', $pass->currency);
        }

        $expected = $this->centavos($pass->amount);
        if (!isset($invoice['amount']) || $this->centavos($invoice['amount']) !== $expected) {
            return sprintf('amount %s != %s', $invoice['amount'] ?? '(none)', $pass->amount);
        }

        // A paid invoice reports what was actually collected; never accept less.
        if (isset($invoice['paid_amount']) && $this->centavos($invoice['paid_amount']) < $expected) {
            return sprintf('paid_amount %s < %s', $invoice['paid_amount'], $pass->amount);
        }

        return null;
    }

    /**
     * Money as integer centavos, so 174.07 compares exactly.
     *
     * @param mixed $amount Decimal string or number.
     * @return int
     */
    private function centavos(mixed $amount): int
    {
        return (int)round((float)$amount * 100);
    }
}

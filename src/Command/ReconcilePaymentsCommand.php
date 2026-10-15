<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\Pass;
use App\Payment\XenditException;
use App\Service\PassService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Throwable;

/**
 * Re-checks pending passes against Xendit and repairs anything the webhook
 * missed (a callback that never arrived, or one that failed every retry).
 *
 *   bin/cake reconcile_payments --dry-run     # report only, write nothing
 *   bin/cake reconcile_payments               # apply
 *
 * For each `pending` pass older than --min-age minutes (and newer than
 * --max-age hours), Xendit's invoice is fetched and applied with the same
 * rules as the webhook (App\Service\PassService): paid → granted, expired →
 * expired, still pending → left alone, mismatch → reported, not granted.
 * A pass whose invoice Xendit doesn't know about is marked `failed` once it is
 * older than --fail-after hours (checkout died before the invoice existed).
 *
 * Safe to run any time and repeatedly (every write is conditional on the pass
 * still being pending). Exits non-zero when anything needs a human — Xendit
 * unreachable, a mismatch, or an error — so a scheduler can alert on it.
 */
class ReconcilePaymentsCommand extends Command
{
    /**
     * @param \Cake\Console\ConsoleOptionParser $parser Parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Re-check pending passes against Xendit and repair missed payments.')
            ->addOption('dry-run', ['boolean' => true, 'help' => 'Report what would change; write nothing.'])
            ->addOption('min-age', [
                'default' => '10',
                'help' => 'Skip passes younger than this many minutes (the webhook is probably still on its way).',
            ])
            ->addOption('max-age', [
                'default' => '168',
                'help' => 'Ignore passes older than this many hours.',
            ])
            ->addOption('fail-after', [
                'default' => '25',
                'help' => 'Mark a pass failed when Xendit has no invoice for it and it is older than this many hours.',
            ])
            ->addOption('limit', ['default' => '200', 'help' => 'Maximum passes to check in one run.']);
    }

    /**
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $dryRun = (bool)$args->getOption('dry-run');
        $minAge = max(0, (int)$args->getOption('min-age'));
        $maxAge = max(1, (int)$args->getOption('max-age'));
        $failAfter = max(1, (int)$args->getOption('fail-after'));
        $limit = max(1, (int)$args->getOption('limit'));

        /** @var \App\Model\Table\PassesTable $passes */
        $passes = $this->fetchTable('Passes');
        $service = new PassService($passes);

        $now = new DateTime();
        /** @var list<\App\Model\Entity\Pass> $pending */
        $pending = $passes->find()
            ->where([
                'status' => 'pending',
                'created <=' => $now->subMinutes($minAge),
                'created >=' => $now->subHours($maxAge),
            ])
            ->orderBy(['created' => 'ASC'])
            ->limit($limit)
            ->all()
            ->toList();

        $io->out(sprintf(
            '%s%d pending pass(es) between %d min and %d h old.',
            $dryRun ? '[dry run] ' : '',
            count($pending),
            $minAge,
            $maxAge,
        ));

        $counts = [];
        $needsAttention = 0;
        foreach ($pending as $pass) {
            try {
                $result = $this->handle($pass, $service, $dryRun, $now->subHours($failAfter));
            } catch (XenditException $e) {
                $result = 'xendit_unreachable';
                $io->err("  pass {$pass->pass_id} ({$pass->external_id}): " . $e->getMessage());
            } catch (Throwable $e) {
                $result = 'error';
                $io->err("  pass {$pass->pass_id} ({$pass->external_id}): " . $e->getMessage());
            }

            $counts[$result] = ($counts[$result] ?? 0) + 1;
            if (in_array($result, [PassService::MISMATCH, 'xendit_unreachable', 'error'], true)) {
                $needsAttention++;
            }
            if ($result !== PassService::STILL_PENDING) {
                $io->out("  pass {$pass->pass_id} ({$pass->external_id}): {$result}");
            }
        }

        ksort($counts);
        $summary = [];
        foreach ($counts as $result => $n) {
            $summary[] = "{$result}={$n}";
        }
        $io->out('Done' . ($summary !== [] ? ': ' . implode(', ', $summary) : '') . '.');

        if ($needsAttention > 0) {
            $io->error("{$needsAttention} pass(es) need attention (see above).");

            return static::CODE_ERROR;
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Checks one pass. In dry-run mode, reports the outcome without writing.
     *
     * @param \App\Model\Entity\Pass $pass The pass.
     * @param \App\Service\PassService $service Pass service.
     * @param bool $dryRun Whether to write.
     * @param \Cake\I18n\DateTime $failBefore Passes created before this, with no invoice, are failed.
     * @return string
     */
    private function handle(
        Pass $pass,
        PassService $service,
        bool $dryRun,
        DateTime $failBefore,
    ): string {
        $invoice = $service->fetchInvoice($pass);

        if ($invoice === null) {
            $tooOld = $pass->created !== null && $pass->created->lessThan($failBefore);
            if (!$tooOld) {
                return PassService::INVOICE_NOT_FOUND;
            }

            return $dryRun ? 'would_fail' : $service->markFailed($pass);
        }

        if ($dryRun) {
            $decision = $service->decide($pass, $invoice);

            return match ($decision) {
                PassService::GRANTED => 'would_grant',
                PassService::EXPIRED => 'would_expire',
                default => $decision,
            };
        }

        return $service->applyInvoice($pass, $invoice);
    }
}

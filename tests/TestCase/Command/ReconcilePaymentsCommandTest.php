<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Model\Entity\Pass;
use App\Test\TestSuite\ApiTestTrait;
use App\Test\TestSuite\XenditTestTrait;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;

/**
 * App\Command\ReconcilePaymentsCommand Test Case
 *
 * @uses \App\Command\ReconcilePaymentsCommand
 */
class ReconcilePaymentsCommandTest extends TestCase
{
    use ApiTestTrait;
    use ConsoleIntegrationTestTrait;
    use XenditTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Passes',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeXenditEnv();
    }

    protected function tearDown(): void
    {
        $this->restoreEnv();
        parent::tearDown();
    }

    /**
     * A pending pass created $minutesAgo minutes ago.
     *
     * @param int $minutesAgo Age.
     * @param array<string, mixed> $fields Overrides.
     * @return \App\Model\Entity\Pass
     */
    private function pendingPass(int $minutesAgo, array $fields = []): Pass
    {
        $passes = $this->getTableLocator()->get('Passes');
        /** @var \App\Model\Entity\Pass $pass */
        $pass = $passes->saveOrFail($passes->newEntity($fields + [
            'user_id' => 1,
            'reviewer' => 'civil-service',
            'plan_id' => '7-day',
            'amount' => '49.00',
            'currency' => 'PHP',
            'external_id' => 'cp_rec_' . bin2hex(random_bytes(6)),
            'xendit_invoice_id' => 'inv_rec_' . bin2hex(random_bytes(6)),
            'status' => 'pending',
        ], ['accessibleFields' => ['*' => true]]));
        $passes->updateAll(['created' => (new DateTime())->subMinutes($minutesAgo)], ['pass_id' => $pass->pass_id]);

        /** @var \App\Model\Entity\Pass */
        return $passes->get($pass->pass_id);
    }

    private function passStatus(Pass $pass): string
    {
        return (string)$this->getTableLocator()->get('Passes')->get($pass->pass_id)->status;
    }

    /**
     * The webhook never arrived, but Xendit shows the invoice paid: grant it.
     */
    public function testGrantsAMissedPayment(): void
    {
        $pass = $this->pendingPass(30);
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'PAID'));

        $this->exec('reconcile_payments');

        $this->assertExitSuccess();
        $this->assertOutputContains('granted=1');
        $this->assertSame('paid', $this->passStatus($pass));
    }

    public function testDryRunWritesNothing(): void
    {
        $pass = $this->pendingPass(30);
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'PAID'));

        $this->exec('reconcile_payments --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('would_grant=1');
        $this->assertSame('pending', $this->passStatus($pass));
    }

    /**
     * Young passes are left to the webhook, which is probably still on its way.
     */
    public function testSkipsPassesYoungerThanMinAge(): void
    {
        $pass = $this->pendingPass(2);

        $this->exec('reconcile_payments --min-age 10');

        $this->assertExitSuccess();
        $this->assertOutputContains('0 pending pass(es)');
        $this->assertSame('pending', $this->passStatus($pass));
    }

    public function testExpiresInvoicesXenditExpired(): void
    {
        $pass = $this->pendingPass(60 * 25);
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'EXPIRED'));

        $this->exec('reconcile_payments');

        $this->assertExitSuccess();
        $this->assertSame('expired', $this->passStatus($pass));
    }

    /**
     * Checkout died before any invoice existed: once old enough, mark it failed.
     */
    public function testMarksOldPassWithoutInvoiceFailed(): void
    {
        $recent = $this->pendingPass(60, ['xendit_invoice_id' => null]);
        $old = $this->pendingPass(60 * 26, ['xendit_invoice_id' => null]);
        $this->fakeXenditFindByExternalId($recent->external_id, []);
        $this->fakeXenditFindByExternalId($old->external_id, []);

        $this->exec('reconcile_payments --fail-after 25');

        $this->assertExitSuccess();
        $this->assertSame('pending', $this->passStatus($recent));
        $this->assertSame('failed', $this->passStatus($old));
    }

    /**
     * Anything a human must look at makes the run exit non-zero, so a
     * scheduler can alert — but the other passes are still processed.
     */
    public function testExitsWithErrorWhenSomethingNeedsAttention(): void
    {
        $unreachable = $this->pendingPass(30);
        $mismatched = $this->pendingPass(31);
        $fine = $this->pendingPass(32);
        $this->fakeXenditGetInvoiceError((string)$unreachable->xendit_invoice_id, 502);
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($mismatched, 'PAID', ['amount' => 1.0, 'paid_amount' => 1.0]));
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($fine, 'PAID'));

        $this->exec('reconcile_payments');

        $this->assertExitError();
        $this->assertErrorContains('2 pass(es) need attention');
        $this->assertSame('pending', $this->passStatus($unreachable));
        $this->assertSame('pending', $this->passStatus($mismatched));
        $this->assertSame('paid', $this->passStatus($fine));
    }

    /**
     * Running twice is safe: the second run finds nothing left to do.
     */
    public function testIsIdempotent(): void
    {
        $pass = $this->pendingPass(30);
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'PAID'));

        $this->exec('reconcile_payments');
        $expiry = $this->getTableLocator()->get('Passes')->get($pass->pass_id)->expires_at;
        $this->exec('reconcile_payments');

        $this->assertExitSuccess();
        $this->assertOutputContains('0 pending pass(es)');
        $this->assertEquals($expiry, $this->getTableLocator()->get('Passes')->get($pass->pass_id)->expires_at);
    }
}

<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Entity\Pass;
use App\Model\Table\PassesTable;
use App\Payment\XenditException;
use App\Service\PassService;
use App\Test\TestSuite\ApiTestTrait;
use App\Test\TestSuite\XenditTestTrait;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Closure;
use RuntimeException;

/**
 * App\Service\PassService Test Case — the guarantees behind B1: verification
 * against Xendit's record, one grant per pass, and all-or-nothing writes.
 *
 * @uses \App\Service\PassService
 */
class PassServiceTest extends TestCase
{
    use ApiTestTrait;
    use XenditTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Passes',
    ];

    private PassesTable $passes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeXenditEnv();
        /** @var \App\Model\Table\PassesTable $passes */
        $passes = $this->getTableLocator()->get('Passes');
        $this->passes = $passes;
    }

    protected function tearDown(): void
    {
        $this->restoreEnv();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $fields Overrides.
     * @return \App\Model\Entity\Pass
     */
    private function makePass(array $fields = []): Pass
    {
        /** @var \App\Model\Entity\Pass $pass */
        $pass = $this->passes->newEntity($fields + [
            'user_id' => 1,
            'reviewer' => 'civil-service',
            'plan_id' => '7-day',
            'amount' => '49.00',
            'currency' => 'PHP',
            'external_id' => 'cp_svc_' . bin2hex(random_bytes(6)),
            'xendit_invoice_id' => 'inv_svc_' . bin2hex(random_bytes(6)),
            'status' => 'pending',
        ], ['accessibleFields' => ['*' => true]]);

        return $this->passes->saveOrFail($pass);
    }

    private function reload(Pass $pass): Pass
    {
        /** @var \App\Model\Entity\Pass */
        return $this->passes->get($pass->pass_id);
    }

    public function testPaidInvoiceGrantsPass(): void
    {
        $pass = $this->makePass();
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'PAID'));

        $result = (new PassService($this->passes))->reconcile($pass);

        $this->assertSame(PassService::GRANTED, $result);
        $this->assertSame('paid', $this->reload($pass)->status);
    }

    /**
     * Two callbacks for the same payment race: the second one holds a stale
     * copy of the pass that still says `pending`. It must not grant again.
     */
    public function testStaleCopyCannotGrantTwice(): void
    {
        $pass = $this->makePass();
        $invoice = $this->xenditInvoiceFor($pass, 'PAID');
        $staleCopy = $this->reload($pass);
        $service = new PassService($this->passes);

        $this->assertSame(PassService::GRANTED, $service->applyInvoice($pass, $invoice));
        $expiry = $this->reload($pass)->expires_at;

        $this->assertSame('pending', $staleCopy->status);
        $this->assertSame(PassService::ALREADY_HANDLED, $service->applyInvoice($staleCopy, $invoice));
        $this->assertEquals($expiry, $this->reload($pass)->expires_at);
    }

    /**
     * If superseding the previous pass fails, the new pass's grant is rolled
     * back too — never one without the other.
     */
    public function testGrantIsRolledBackWhenSupersedeFails(): void
    {
        $active = $this->makePass([
            'status' => 'paid',
            'paid_at' => (new DateTime())->subDays(2),
            'expires_at' => (new DateTime())->addDays(5),
        ]);
        $renewal = $this->makePass();

        $failingTable = new class ([
            'alias' => 'Passes',
            'table' => 'passes',
            'entityClass' => Pass::class,
            'connection' => ConnectionManager::get('test'),
        ]) extends PassesTable {
            public function updateAll(
                QueryExpression|Closure|array|string $fields,
                QueryExpression|Closure|array|string|null $conditions,
            ): int {
                if (is_array($fields) && ($fields['status'] ?? null) === 'superseded') {
                    throw new RuntimeException('simulated database failure');
                }

                return parent::updateAll($fields, $conditions);
            }
        };

        try {
            (new PassService($failingTable))->applyInvoice($renewal, $this->xenditInvoiceFor($renewal, 'PAID'));
            $this->fail('Expected the simulated failure to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated database failure', $e->getMessage());
        }

        $this->assertSame('pending', $this->reload($renewal)->status);
        $this->assertNull($this->reload($renewal)->expires_at);
        $this->assertSame('paid', $this->reload($active)->status);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function mismatchedInvoices(): array
    {
        return [
            'amount' => [['amount' => 4.9, 'paid_amount' => 4.9]],
            'currency' => [['currency' => 'USD']],
            'external_id' => [['external_id' => 'cp_someone_else']],
            'invoice id' => [['id' => 'inv_other']],
            'underpaid' => [['paid_amount' => 40.0]],
        ];
    }

    /**
     * @param array<string, mixed> $override Field that makes the invoice wrong.
     * @dataProvider mismatchedInvoices
     */
    public function testMismatchedInvoiceIsNotGranted(array $override): void
    {
        $pass = $this->makePass();
        $result = (new PassService($this->passes))
            ->applyInvoice($pass, $this->xenditInvoiceFor($pass, 'PAID', $override));

        $this->assertSame(PassService::MISMATCH, $result);
        $this->assertSame('pending', $this->reload($pass)->status);
    }

    public function testAmountComparisonIsExactToTheCentavo(): void
    {
        $pass = $this->makePass(['plan_id' => '30-day', 'amount' => '174.07']);
        $result = (new PassService($this->passes))
            ->applyInvoice($pass, $this->xenditInvoiceFor($pass, 'PAID', ['amount' => 174.07, 'paid_amount' => 174.07]));

        $this->assertSame(PassService::GRANTED, $result);
    }

    /**
     * Checkout couldn't store the invoice id: find the invoice by our
     * external_id instead, and back-fill the id.
     */
    public function testFindsInvoiceByExternalIdWhenIdWasNeverStored(): void
    {
        $pass = $this->makePass(['xendit_invoice_id' => null]);
        $invoice = $this->xenditInvoiceFor($pass, 'PAID', ['id' => 'inv_found_by_ref']);
        $this->fakeXenditFindByExternalId($pass->external_id, [$invoice]);

        $result = (new PassService($this->passes))->reconcile($pass);

        $this->assertSame(PassService::GRANTED, $result);
        $this->assertSame('inv_found_by_ref', $this->reload($pass)->xendit_invoice_id);
    }

    public function testPendingInvoiceChangesNothing(): void
    {
        $pass = $this->makePass();
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'PENDING'));

        $this->assertSame(PassService::STILL_PENDING, (new PassService($this->passes))->reconcile($pass));
        $this->assertSame('pending', $this->reload($pass)->status);
    }

    public function testUnreachableXenditThrowsXenditException(): void
    {
        $pass = $this->makePass();
        $this->fakeXenditGetInvoiceError((string)$pass->xendit_invoice_id, 503);

        $this->expectException(XenditException::class);
        (new PassService($this->passes))->reconcile($pass);
    }

    public function testMarkFailedOnlyAffectsPendingPasses(): void
    {
        $pending = $this->makePass();
        $paid = $this->makePass(['status' => 'paid', 'paid_at' => new DateTime(), 'expires_at' => (new DateTime())->addDays(7)]);
        $service = new PassService($this->passes);

        $this->assertSame(PassService::FAILED, $service->markFailed($pending));
        $this->assertSame(PassService::ALREADY_HANDLED, $service->markFailed($paid));
        $this->assertSame('failed', $this->reload($pending)->status);
        $this->assertSame('paid', $this->reload($paid)->status);
    }
}

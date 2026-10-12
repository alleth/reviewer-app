<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Entity\Pass;
use App\Test\TestSuite\ApiTestTrait;
use App\Test\TestSuite\XenditTestTrait;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use DateTimeInterface;

/**
 * App\Controller\PaymentsController Test Case
 *
 * Checkout against a fake Xendit, and the webhook: every callback is verified
 * against Xendit's own invoice record before anything is granted, and failures
 * answer 5xx so Xendit retries (B1).
 *
 * @uses \App\Controller\PaymentsController
 */
class PaymentsControllerTest extends TestCase
{
    use ApiTestTrait;
    use IntegrationTestTrait;
    use XenditTestTrait;

    /**
     * Fixtures
     *
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
     * Inserts a pass row for user 1.
     *
     * @param array<string, mixed> $fields Overrides.
     * @return \App\Model\Entity\Pass
     */
    private function makePass(array $fields = []): Pass
    {
        $passes = $this->getTableLocator()->get('Passes');
        /** @var \App\Model\Entity\Pass $pass */
        $pass = $passes->newEntity($fields + [
            'user_id' => 1,
            'reviewer' => 'civil-service',
            'plan_id' => '7-day',
            'amount' => '49.00',
            'currency' => 'PHP',
            'external_id' => 'cp_test_' . bin2hex(random_bytes(6)),
            'xendit_invoice_id' => 'inv_test_' . bin2hex(random_bytes(6)),
            'status' => 'pending',
        ], ['accessibleFields' => ['*' => true]]);

        return $passes->saveOrFail($pass);
    }

    /**
     * @param int $passId Pass id.
     * @return \App\Model\Entity\Pass
     */
    private function reloadPass(int $passId): Pass
    {
        /** @var \App\Model\Entity\Pass */
        return $this->getTableLocator()->get('Passes')->get($passId);
    }

    /**
     * DATETIME columns keep whole seconds, so compare at that precision.
     *
     * @param \DateTimeInterface $expected Expected time.
     * @param \DateTimeInterface|null $actual Actual time.
     * @return void
     */
    private function assertSameSecond(DateTimeInterface $expected, ?DateTimeInterface $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame($expected->format('Y-m-d H:i:s'), $actual->format('Y-m-d H:i:s'));
    }

    /**
     * @return void
     * @uses \App\Controller\PaymentsController::checkout()
     */
    public function testCheckoutRequiresLogin(): void
    {
        $this->asAjax();
        $this->post('/api/checkout', ['reviewer' => 'civil-service', 'plan_id' => '7-day']);

        $this->assertResponseCode(401);
    }

    /**
     * @return void
     * @uses \App\Controller\PaymentsController::checkout()
     */
    public function testCheckoutWithoutAjaxHeaderIsForbidden(): void
    {
        $this->loginAs(1);
        $this->post('/api/checkout', ['reviewer' => 'civil-service', 'plan_id' => '7-day']);

        $this->assertResponseCode(403);
    }

    /**
     * @return void
     * @uses \App\Controller\PaymentsController::checkout()
     */
    public function testCheckoutRejectsUnknownPlanAndReviewer(): void
    {
        $this->loginAs(1);
        $this->asAjax();
        $this->post('/api/checkout', ['reviewer' => 'civil-service', 'plan_id' => '365-day']);
        $this->assertResponseCode(422);

        $this->loginAs(1);
        $this->asAjax();
        $this->post('/api/checkout', ['reviewer' => 'nursing', 'plan_id' => '7-day']);
        $this->assertResponseCode(422);
    }

    /**
     * The amount comes from the server-side price list, never the client.
     *
     * @return void
     * @uses \App\Controller\PaymentsController::checkout()
     */
    public function testCheckoutCreatesPendingPassAndInvoice(): void
    {
        $invoice = $this->fakeXenditCreateInvoice();

        $this->loginAs(1);
        $this->asAjax();
        $this->post('/api/checkout', ['reviewer' => 'civil-service', 'plan_id' => '30-day', 'amount' => '1.00']);

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertTrue($body['success']);
        $this->assertSame($invoice['invoice_url'], $body['invoice_url']);

        $pass = $this->getTableLocator()->get('Passes')->find()->where(['user_id' => 1])->firstOrFail();
        $this->assertSame('pending', $pass->status);
        $this->assertSame('30-day', $pass->plan_id);
        $this->assertSame('174.07', (string)$pass->amount);
        $this->assertSame($invoice['id'], $pass->xendit_invoice_id);
        $this->assertSame($invoice['invoice_url'], $pass->invoice_url);
    }

    /**
     * A repeat click within 5 minutes gets the same invoice, not a second one.
     *
     * @return void
     * @uses \App\Controller\PaymentsController::checkout()
     */
    public function testCheckoutReusesRecentPendingInvoice(): void
    {
        $existing = $this->makePass([
            'plan_id' => '7-day',
            'invoice_url' => 'https://checkout-staging.xendit.co/web/existing',
        ]);
        // If checkout wrongly called Xendit again, it would get this other URL.
        $this->fakeXenditCreateInvoice(['invoice_url' => 'https://checkout-staging.xendit.co/web/second']);

        $this->loginAs(1);
        $this->asAjax();
        $this->post('/api/checkout', ['reviewer' => 'civil-service', 'plan_id' => '7-day']);

        $this->assertResponseOk();
        $this->assertSame($existing->invoice_url, $this->responseJson()['invoice_url']);
        $this->assertSame(1, $this->getTableLocator()->get('Passes')->find()->count());
    }

    /**
     * @return void
     * @uses \App\Controller\PaymentsController::checkout()
     */
    public function testCheckoutReportsXenditFailure(): void
    {
        $this->fakeXenditCreateInvoiceFailure(500);

        $this->loginAs(1);
        $this->asAjax();
        $this->post('/api/checkout', ['reviewer' => 'civil-service', 'plan_id' => '7-day']);

        $this->assertResponseCode(500);
        $body = $this->responseJson();
        $this->assertFalse($body['success']);
        // The upstream error stays in the server log, not the response.
        $this->assertStringNotContainsString('SERVER_ERROR', (string)$this->_response->getBody());
    }

    /**
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookRejectsWrongToken(): void
    {
        $pass = $this->makePass();
        $this->postXenditWebhook(['external_id' => $pass->external_id, 'status' => 'PAID'], 'wrong-token');

        $this->assertResponseCode(401);
        $this->assertSame('pending', $this->reloadPass($pass->pass_id)->status);
    }

    /**
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookPaidActivatesPass(): void
    {
        $pass = $this->makePass(['plan_id' => '7-day']);
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'PAID'));
        $this->postXenditWebhook(['external_id' => $pass->external_id, 'status' => 'PAID']);

        $this->assertResponseOk();
        $this->assertSame('granted', $this->responseJson()['result']);
        $paid = $this->reloadPass($pass->pass_id);
        $this->assertSame('paid', $paid->status);
        $this->assertNotNull($paid->paid_at);
        $this->assertSameSecond((new DateTime())->addDays(7), $paid->expires_at);
    }

    /**
     * The callback body is only a pointer: a "PAID" callback for an invoice
     * Xendit itself reports as underpaid grants nothing.
     *
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookDoesNotGrantWhenXenditAmountDiffers(): void
    {
        $pass = $this->makePass(['plan_id' => '30-day', 'amount' => '174.07']);
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'PAID', ['amount' => 1.0, 'paid_amount' => 1.0]));

        $this->postXenditWebhook(['external_id' => $pass->external_id, 'status' => 'PAID', 'amount' => 174.07]);

        // Retrying can't fix a mismatch, so Xendit gets a 2xx; it's logged instead.
        $this->assertResponseOk();
        $this->assertSame('mismatch', $this->responseJson()['result']);
        $this->assertSame('pending', $this->reloadPass($pass->pass_id)->status);
    }

    /**
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookReturns503WhenXenditIsUnreachable(): void
    {
        $pass = $this->makePass();
        $this->fakeXenditGetInvoiceError((string)$pass->xendit_invoice_id, 502);

        $this->postXenditWebhook(['external_id' => $pass->external_id, 'status' => 'PAID']);

        $this->assertResponseCode(503);
        $this->assertSame('pending', $this->reloadPass($pass->pass_id)->status);
    }

    /**
     * A "PAID" callback that Xendit's API doesn't confirm yet is retried, not dropped.
     *
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookReturns503WhenXenditDoesNotShowPaymentYet(): void
    {
        $pass = $this->makePass();
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'PENDING'));

        $this->postXenditWebhook(['external_id' => $pass->external_id, 'status' => 'PAID']);

        $this->assertResponseCode(503);
        $this->assertSame('pending', $this->reloadPass($pass->pass_id)->status);
    }

    /**
     * A failure on our side answers 5xx (so Xendit retries) and writes nothing.
     * Before B1 this returned 200 and the payment was silently lost.
     *
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookReturns500AndWritesNothingWhenGrantFails(): void
    {
        // A plan id the server no longer knows: granting must fail loudly.
        $pass = $this->makePass(['plan_id' => 'retired-plan']);
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'PAID'));

        $this->postXenditWebhook(['external_id' => $pass->external_id, 'status' => 'PAID']);

        $this->assertResponseCode(500);
        $unchanged = $this->reloadPass($pass->pass_id);
        $this->assertSame('pending', $unchanged->status);
        $this->assertNull($unchanged->expires_at);
    }

    /**
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookAcknowledgesUnknownPass(): void
    {
        $this->postXenditWebhook(['external_id' => 'cp_not_ours', 'status' => 'PAID']);

        $this->assertResponseOk();
        $this->assertSame('unknown_pass', $this->responseJson()['result']);
    }

    /**
     * Xendit has no such invoice (e.g. a forged callback with a leaked token).
     *
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookDoesNotGrantWhenXenditHasNoInvoice(): void
    {
        $pass = $this->makePass();
        $this->fakeXenditGetInvoiceError((string)$pass->xendit_invoice_id, 404);

        $this->postXenditWebhook(['external_id' => $pass->external_id, 'status' => 'PAID']);

        $this->assertResponseOk();
        $this->assertSame('invoice_not_found', $this->responseJson()['result']);
        $this->assertSame('pending', $this->reloadPass($pass->pass_id)->status);
    }

    /**
     * Buying again while a pass is active stacks the new days on top and
     * supersedes the old pass — one active pass per (user, reviewer).
     *
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookPaidStacksOnActivePass(): void
    {
        $activeUntil = (new DateTime())->addDays(3);
        $active = $this->makePass([
            'plan_id' => '7-day',
            'status' => 'paid',
            'paid_at' => (new DateTime())->subDays(4),
            'expires_at' => $activeUntil,
        ]);
        $renewal = $this->makePass(['plan_id' => '30-day', 'amount' => '174.07']);
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($renewal, 'SETTLED'));

        $this->postXenditWebhook(['external_id' => $renewal->external_id, 'status' => 'SETTLED']);

        $this->assertResponseOk();
        $this->assertSameSecond($activeUntil->addDays(30), $this->reloadPass($renewal->pass_id)->expires_at);
        $this->assertSame('superseded', $this->reloadPass($active->pass_id)->status);
    }

    /**
     * Xendit may deliver the same callback more than once.
     *
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookIsIdempotent(): void
    {
        $pass = $this->makePass(['plan_id' => '7-day']);
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'PAID'));
        $this->postXenditWebhook(['external_id' => $pass->external_id, 'status' => 'PAID']);
        $firstExpiry = $this->reloadPass($pass->pass_id)->expires_at;

        $this->postXenditWebhook(['external_id' => $pass->external_id, 'status' => 'PAID']);

        $this->assertResponseOk();
        $this->assertSame('already_handled', $this->responseJson()['result']);
        $this->assertEquals($firstExpiry, $this->reloadPass($pass->pass_id)->expires_at);
    }

    /**
     * @return void
     * @uses \App\Controller\PaymentsController::webhook()
     */
    public function testWebhookExpiredMarksPassExpired(): void
    {
        $pass = $this->makePass();
        $this->fakeXenditGetInvoice($this->xenditInvoiceFor($pass, 'EXPIRED'));
        $this->postXenditWebhook(['external_id' => $pass->external_id, 'status' => 'EXPIRED']);

        $this->assertResponseOk();
        $expired = $this->reloadPass($pass->pass_id);
        $this->assertSame('expired', $expired->status);
        $this->assertNull($expired->expires_at);
    }

    /**
     * @return void
     * @uses \App\Controller\PaymentsController::myPasses()
     */
    public function testMyPassesListsOnlyActivePaidPasses(): void
    {
        $this->makePass(['status' => 'paid', 'paid_at' => new DateTime(), 'expires_at' => (new DateTime())->addDays(7)]);
        $this->makePass(['status' => 'pending']);
        $this->makePass([
            'status' => 'paid',
            'paid_at' => (new DateTime())->subDays(40),
            'expires_at' => (new DateTime())->subDays(10),
        ]);

        $this->loginAs(1);
        $this->get('/api/purchases');

        $this->assertResponseOk();
        $purchases = $this->responseJson()['purchases'];
        $this->assertCount(1, $purchases);
        $this->assertSame('7-day', $purchases[0]['planId']);
    }
}

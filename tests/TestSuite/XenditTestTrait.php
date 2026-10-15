<?php
declare(strict_types=1);

namespace App\Test\TestSuite;

use App\Model\Entity\Pass;
use Cake\Http\TestSuite\HttpClientTrait;

/**
 * A fake Xendit for tests. App\Payment\Xendit talks to Xendit through
 * Cake\Http\Client, so its calls can be answered by Client mock responses —
 * no production code needs to change to make it testable.
 *
 * No real network calls: fakeXenditEnv() registers a mock up front, and once
 * any mock exists Cake's Client refuses unmatched requests (a
 * MissingResponseException, which Xendit wraps as XenditException) instead of
 * sending them. The fake secret key is a second safety net.
 *
 * Requires ApiTestTrait (for setEnv()/restoreEnv()). Call fakeXenditEnv() in
 * setUp() and restoreEnv() in tearDown(); HttpClientTrait clears the mocks
 * after each test.
 */
trait XenditTestTrait
{
    use HttpClientTrait;

    protected string $xenditWebhookToken = 'test-callback-token';

    /**
     * Test credentials and the no-network guard.
     *
     * @return void
     */
    protected function fakeXenditEnv(): void
    {
        $this->setEnv('XENDIT_SECRET_KEY', 'xnd_development_fake_for_tests');
        $this->setEnv('XENDIT_WEBHOOK_TOKEN', $this->xenditWebhookToken);
        $this->setEnv('FRONTEND_URL', 'https://career-pass.test');

        // Never matched; its only job is to switch Client into mock-only mode.
        $this->mockClientGet('https://xendit.invalid/no-network-guard', $this->newClientResponse(418));
    }

    /**
     * Answers the next "create invoice" call with a successful invoice.
     *
     * @param array<string, mixed> $invoice Fields to override in the fake response.
     * @return array<string, mixed> The invoice that will be returned.
     */
    protected function fakeXenditCreateInvoice(array $invoice = []): array
    {
        $invoice += [
            'id' => 'inv_fake_' . bin2hex(random_bytes(4)),
            'invoice_url' => 'https://checkout-staging.xendit.co/web/fake',
            'status' => 'PENDING',
        ];
        $this->mockClientPost(
            'https://api.xendit.co/v2/invoices',
            $this->newClientResponse(200, ['Content-Type: application/json'], (string)json_encode($invoice)),
        );

        return $invoice;
    }

    /**
     * Makes the next "create invoice" call fail with an HTTP error.
     *
     * @param int $status HTTP status Xendit answers with.
     * @return void
     */
    protected function fakeXenditCreateInvoiceFailure(int $status = 500): void
    {
        $this->mockClientPost(
            'https://api.xendit.co/v2/invoices',
            $this->newClientResponse($status, ['Content-Type: application/json'], '{"error_code":"SERVER_ERROR"}'),
        );
    }

    /**
     * Xendit's invoice record for $pass, as GET /v2/invoices/{id} returns it.
     *
     * @param \App\Model\Entity\Pass $pass The pass the invoice belongs to.
     * @param string $status Xendit status (PENDING, PAID, SETTLED, EXPIRED).
     * @param array<string, mixed> $overrides Fields to change (e.g. a wrong amount).
     * @return array<string, mixed>
     */
    protected function xenditInvoiceFor(Pass $pass, string $status, array $overrides = []): array
    {
        $invoice = $overrides + [
            'id' => (string)$pass->xendit_invoice_id !== '' ? $pass->xendit_invoice_id : 'inv_lookup_' . $pass->pass_id,
            'external_id' => $pass->external_id,
            'status' => $status,
            'amount' => (float)$pass->amount,
            'currency' => $pass->currency,
        ];
        if (in_array($status, ['PAID', 'SETTLED'], true) && !array_key_exists('paid_amount', $overrides)) {
            $invoice['paid_amount'] = $invoice['amount'];
        }

        return $invoice;
    }

    /**
     * Answers GET /v2/invoices/{id} with $invoice.
     *
     * @param array<string, mixed> $invoice The invoice record (see xenditInvoiceFor()).
     * @return void
     */
    protected function fakeXenditGetInvoice(array $invoice): void
    {
        $this->mockClientGet(
            'https://api.xendit.co/v2/invoices/' . rawurlencode((string)$invoice['id']),
            $this->newClientResponse(200, ['Content-Type: application/json'], (string)json_encode($invoice)),
        );
    }

    /**
     * Makes GET /v2/invoices/{id} answer with an HTTP error (404 = no such invoice).
     *
     * @param string $invoiceId Invoice id.
     * @param int $status HTTP status.
     * @return void
     */
    protected function fakeXenditGetInvoiceError(string $invoiceId, int $status = 500): void
    {
        $this->mockClientGet(
            'https://api.xendit.co/v2/invoices/' . rawurlencode($invoiceId),
            $this->newClientResponse($status, ['Content-Type: application/json'], '{"error_code":"ERROR"}'),
        );
    }

    /**
     * Answers GET /v2/invoices?external_id=… with $invoices.
     *
     * @param string $externalId Our reference.
     * @param list<array<string, mixed>> $invoices Matching invoices ([] = none).
     * @return void
     */
    protected function fakeXenditFindByExternalId(string $externalId, array $invoices): void
    {
        $this->mockClientGet(
            'https://api.xendit.co/v2/invoices?' . http_build_query(['external_id' => $externalId]),
            $this->newClientResponse(200, ['Content-Type: application/json'], (string)json_encode($invoices)),
        );
    }

    /**
     * Posts an invoice callback to the webhook, as Xendit would.
     *
     * @param array<string, mixed> $payload Callback body (external_id, status, …).
     * @param string|null $token Callback token header; defaults to the configured one.
     * @return void
     */
    protected function postXenditWebhook(array $payload, ?string $token = null): void
    {
        $this->configRequest([
            'headers' => ['x-callback-token' => $token ?? $this->xenditWebhookToken],
        ]);
        $this->post('/api/xendit/webhook', $payload);
    }
}

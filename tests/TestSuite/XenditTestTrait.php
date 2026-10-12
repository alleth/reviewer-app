<?php
declare(strict_types=1);

namespace App\Test\TestSuite;

use Cake\Http\TestSuite\HttpClientTrait;

/**
 * A fake Xendit for tests. App\Payment\Xendit talks to Xendit through
 * Cake\Http\Client, so its calls can be answered by Client mock responses —
 * no production code needs to change to make it testable.
 *
 * A call with no matching mock would still go out over the network, which is
 * why fakeXenditEnv() swaps in a key Xendit will reject: an unmocked call
 * fails authentication instead of creating anything real.
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
     * Test credentials, so nothing can authenticate against the real Xendit.
     *
     * @return void
     */
    protected function fakeXenditEnv(): void
    {
        $this->setEnv('XENDIT_SECRET_KEY', 'xnd_development_fake_for_tests');
        $this->setEnv('XENDIT_WEBHOOK_TOKEN', $this->xenditWebhookToken);
        $this->setEnv('FRONTEND_URL', 'https://career-pass.test');
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

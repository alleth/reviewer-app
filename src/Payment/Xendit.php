<?php
declare(strict_types=1);

namespace App\Payment;

use Cake\Http\Client;
use RuntimeException;

/**
 * Thin wrapper over the Xendit Invoice API (https://api.xendit.co/v2/invoices).
 * Called directly over HTTPS — same approach as App\Mailer\Transport\BrevoTransport.
 *
 * The secret key comes from XENDIT_SECRET_KEY (an `xnd_development_...` key while
 * the business is in Test Mode; swap for the live key once verified). Auth is
 * HTTP Basic with the key as the username and an empty password.
 */
final class Xendit
{
    private const INVOICES_URL = 'https://api.xendit.co/v2/invoices';

    /**
     * Creates a hosted-checkout invoice. Returns the decoded response
     * (`id`, `invoice_url`, `status`, …).
     *
     * `$idempotencyKey`, when given, is sent as `X-IDEMPOTENCY-KEY` — if Xendit
     * has already seen that key (within its retention window), it returns the
     * original invoice instead of creating a second one. This only guards
     * network-level retries of *this exact call* (e.g. our own request timing
     * out after Xendit actually processed it); it's not a substitute for
     * PaymentsController::checkout()'s own recent-pending-pass check, which is
     * what actually stops a double-click/extra-tab from reaching this method
     * twice with two different (fresh, random) external_ids in the first place.
     *
     * @param array<string, mixed> $params Invoice fields (external_id, amount, payer_email, …).
     * @param string|null $idempotencyKey Caller-supplied idempotency key, if any.
     * @return array<string, mixed>
     */
    public static function createInvoice(array $params, ?string $idempotencyKey = null): array
    {
        $key = (string)env('XENDIT_SECRET_KEY');
        if ($key === '') {
            throw new RuntimeException('Xendit: XENDIT_SECRET_KEY is not configured.');
        }

        $options = [
            'type' => 'json',
            'auth' => ['username' => $key, 'password' => ''],
        ];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $options['headers'] = ['X-IDEMPOTENCY-KEY' => $idempotencyKey];
        }

        $response = (new Client())->post(self::INVOICES_URL, json_encode($params), $options);

        if (!$response->isOk()) {
            throw new RuntimeException(sprintf(
                'Xendit invoice create failed %d: %s',
                $response->getStatusCode(),
                $response->getStringBody(),
            ));
        }

        return (array)$response->getJson();
    }
}

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
     * @param array<string, mixed> $params Invoice fields (external_id, amount, payer_email, …).
     * @return array<string, mixed>
     */
    public static function createInvoice(array $params): array
    {
        $key = (string)env('XENDIT_SECRET_KEY');
        if ($key === '') {
            throw new RuntimeException('Xendit: XENDIT_SECRET_KEY is not configured.');
        }

        $response = (new Client())->post(self::INVOICES_URL, json_encode($params), [
            'type' => 'json',
            'auth' => ['username' => $key, 'password' => ''],
        ]);

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

<?php
declare(strict_types=1);

namespace App\Payment;

use Cake\Http\Client;
use Cake\Http\Client\Response;
use Throwable;

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
        $options = ['type' => 'json'] + self::authOptions();
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $options['headers'] = ['X-IDEMPOTENCY-KEY' => $idempotencyKey];
        }

        $response = self::send(
            'invoice create',
            fn(Client $client) => $client->post(self::INVOICES_URL, (string)json_encode($params), $options),
        );

        return (array)$response->getJson();
    }

    /**
     * Fetches one invoice by Xendit's invoice id — Xendit's own record of the
     * status, amount and currency, used to verify a webhook before granting.
     *
     * @param string $invoiceId Xendit invoice id (`id` from createInvoice()).
     * @return array<string, mixed>|null The invoice, or null if Xendit has no such invoice.
     * @throws \App\Payment\XenditException When Xendit can't be reached or errors.
     */
    public static function getInvoice(string $invoiceId): ?array
    {
        $response = self::send(
            'invoice fetch',
            fn(Client $client) => $client->get(
                self::INVOICES_URL . '/' . rawurlencode($invoiceId),
                [],
                self::authOptions(),
            ),
            [404],
        );
        if ($response->getStatusCode() === 404) {
            return null;
        }

        return (array)$response->getJson();
    }

    /**
     * Finds the invoice created for one of our passes by its external_id —
     * the fallback when the Xendit invoice id was never saved on the pass
     * (e.g. checkout's save failed right after the invoice was created).
     *
     * @param string $externalId Our reference (passes.external_id).
     * @return array<string, mixed>|null The newest matching invoice, or null if none exists.
     * @throws \App\Payment\XenditException When Xendit can't be reached or errors.
     */
    public static function findInvoiceByExternalId(string $externalId): ?array
    {
        $response = self::send(
            'invoice search',
            fn(Client $client) => $client->get(self::INVOICES_URL, ['external_id' => $externalId], self::authOptions()),
            [404],
        );
        if ($response->getStatusCode() === 404) {
            return null;
        }

        $invoices = (array)$response->getJson();
        $first = $invoices[0] ?? null;

        return is_array($first) ? $first : null;
    }

    /**
     * @return array<string, mixed> Client options carrying HTTP Basic auth with the secret key.
     */
    private static function authOptions(): array
    {
        $key = (string)env('XENDIT_SECRET_KEY');
        if ($key === '') {
            throw new XenditException('Xendit: XENDIT_SECRET_KEY is not configured.');
        }

        return ['auth' => ['username' => $key, 'password' => '']];
    }

    /**
     * Runs one request and turns transport errors and non-2xx answers (other
     * than the statuses in $allowed) into XenditException.
     *
     * @param string $what Short description for the error message.
     * @param callable(\Cake\Http\Client): \Cake\Http\Client\Response $request The request to run.
     * @param list<int> $allowed Non-2xx statuses the caller handles itself.
     * @return \Cake\Http\Client\Response
     */
    private static function send(string $what, callable $request, array $allowed = []): Response
    {
        try {
            $response = $request(new Client(['timeout' => 15]));
        } catch (XenditException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new XenditException("Xendit {$what} failed: " . $e->getMessage(), 0, $e);
        }

        if (!$response->isSuccess() && !in_array($response->getStatusCode(), $allowed, true)) {
            throw new XenditException(sprintf(
                'Xendit %s failed %d: %s',
                $what,
                $response->getStatusCode(),
                $response->getStringBody(),
            ));
        }

        return $response;
    }
}

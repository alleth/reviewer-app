<?php
declare(strict_types=1);

namespace App\Http;

use Cake\Http\ServerRequest;

/**
 * The client's IP address as seen behind Railway's edge proxy (H4).
 *
 * PHP's REMOTE_ADDR is Railway's internal proxy hop (a different 100.64.0.0/10
 * address per request), so the address comes from `X-Real-IP`, which Railway's
 * edge sets to the connecting client and overwrites if the client sends one.
 * `X-Forwarded-For` is not used: its left entry can come from the client and
 * its rightmost entry is not the client (both checked on staging).
 * RATE_LIMIT_IP_HEADER selects a different header; for a list-valued one the
 * rightmost entry is used. Anything that isn't a valid IP falls back to
 * REMOTE_ADDR.
 */
final class ClientIp
{
    /**
     * The client IP for $request, or `unknown` when there is none.
     */
    public static function from(ServerRequest $request): string
    {
        $header = trim((string)env('RATE_LIMIT_IP_HEADER')) ?: 'X-Real-IP';
        $raw = $request->getHeaderLine($header);
        if ($raw !== '') {
            $parts = array_map('trim', explode(',', $raw));
            $last = (string)end($parts);
            if (filter_var($last, FILTER_VALIDATE_IP) !== false) {
                return $last;
            }
        }

        $remote = (string)$request->getEnv('REMOTE_ADDR');

        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : 'unknown';
    }
}

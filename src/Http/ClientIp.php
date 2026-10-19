<?php
declare(strict_types=1);

namespace App\Http;

use Cake\Http\ServerRequest;

/**
 * The client's IP address as seen behind Railway's edge proxy (H4).
 *
 * PHP's REMOTE_ADDR is the proxy, so the address comes from a forwarding
 * header — by default the RIGHTMOST `X-Forwarded-For` entry, which is the one
 * Railway's proxy appends. A client can put anything in the header, but only
 * to the left of that entry, so it can't choose the value used here.
 * RATE_LIMIT_IP_HEADER selects a different header if Railway's turns out to
 * be another one (checked on staging before per-IP limits are enforced).
 * Anything that isn't a valid IP falls back to REMOTE_ADDR.
 */
final class ClientIp
{
    /**
     * The client IP for $request, or `unknown` when there is none.
     */
    public static function from(ServerRequest $request): string
    {
        $header = trim((string)env('RATE_LIMIT_IP_HEADER')) ?: 'X-Forwarded-For';
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

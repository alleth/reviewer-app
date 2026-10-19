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
        return self::select($request)[0];
    }

    /**
     * TEMPORARY staging diagnostic (H4): which forwarding headers the request
     * carries and which source from() picked. Presence flags and a count only,
     * never an address, part of one or a hash of one.
     *
     * @return array{xff: bool, xff_entries: int, x_real_ip: bool, x_envoy_external_address: bool, xff_left_equals_real_ip: bool, xff_right_is_internal: bool, remote_addr_fallback: bool, source: string}
     */
    public static function diagnose(ServerRequest $request): array
    {
        $xff = $request->getHeaderLine('X-Forwarded-For');
        $parts = $xff === '' ? [] : array_map('trim', explode(',', $xff));
        $realIp = trim($request->getHeaderLine('X-Real-IP'));
        $source = self::select($request)[1];

        return [
            'xff' => $xff !== '',
            'xff_entries' => count($parts),
            'x_real_ip' => $realIp !== '',
            'x_envoy_external_address' => $request->getHeaderLine('X-Envoy-External-Address') !== '',
            'xff_left_equals_real_ip' => $parts !== [] && $realIp !== '' && $parts[0] === $realIp,
            'xff_right_is_internal' => $parts !== [] && self::isInternal((string)end($parts)),
            'remote_addr_fallback' => $source === 'remote_addr' || $source === 'unknown',
            'source' => $source,
        ];
    }

    /**
     * Whether $ip is a valid non-public address: private, reserved, loopback,
     * link-local or carrier-grade NAT (100.64.0.0/10, Railway's internal range).
     */
    private static function isInternal(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        $long = ip2long($ip);

        return $long !== false && ($long & 0xFFC00000) === (100 << 24 | 64 << 16);
    }

    /**
     * The client IP and where it came from: `xff_rightmost`, `custom_header`
     * (RATE_LIMIT_IP_HEADER), `remote_addr` or `unknown`.
     *
     * @return array{0: string, 1: string}
     */
    private static function select(ServerRequest $request): array
    {
        $custom = trim((string)env('RATE_LIMIT_IP_HEADER'));
        $raw = $request->getHeaderLine($custom ?: 'X-Forwarded-For');
        if ($raw !== '') {
            $parts = array_map('trim', explode(',', $raw));
            $last = (string)end($parts);
            if (filter_var($last, FILTER_VALIDATE_IP) !== false) {
                return [$last, $custom !== '' ? 'custom_header' : 'xff_rightmost'];
            }
        }

        $remote = (string)$request->getEnv('REMOTE_ADDR');

        return filter_var($remote, FILTER_VALIDATE_IP) !== false
            ? [$remote, 'remote_addr']
            : ['unknown', 'unknown'];
    }
}

<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Checks a Cloudflare Turnstile token (H4: register and forgot password).
 * Injected through the DI container so tests can swap it with mockService().
 */
interface TurnstileVerifierInterface
{
    /**
     * Whether a check is configured at all. Without a secret key (local
     * development) the check is skipped.
     */
    public function isEnabled(): bool;

    /**
     * True for a valid token, false for an invalid one, null when Cloudflare
     * couldn't be asked (unreachable, an error, or too slow): callers then
     * fail open.
     */
    public function verify(string $token, string $remoteIp): ?bool;
}

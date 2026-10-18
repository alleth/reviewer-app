<?php
declare(strict_types=1);

namespace App\Security;

use Cake\Http\Client;
use Throwable;

/**
 * The real Turnstile check: POSTs the token to Cloudflare's siteverify
 * endpoint with the secret from TURNSTILE_SECRET_KEY.
 */
class TurnstileVerifier implements TurnstileVerifierInterface
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Longest wait for Cloudflare before failing open. */
    private const TIMEOUT_SECONDS = 3;

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return $this->secret() !== '';
    }

    /**
     * @inheritDoc
     */
    public function verify(string $token, string $remoteIp): ?bool
    {
        try {
            $response = (new Client(['timeout' => self::TIMEOUT_SECONDS]))->post(self::VERIFY_URL, [
                'secret' => $this->secret(),
                'response' => $token,
                'remoteip' => $remoteIp,
            ]);
        } catch (Throwable $e) {
            return null;
        }

        if (!$response->isOk()) {
            return null;
        }
        $body = $response->getJson();

        return is_array($body) && ($body['success'] ?? false) === true;
    }

    /**
     * The configured secret key, or an empty string.
     */
    private function secret(): string
    {
        return trim((string)env('TURNSTILE_SECRET_KEY'));
    }
}

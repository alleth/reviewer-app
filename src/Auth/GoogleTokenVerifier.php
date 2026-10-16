<?php
declare(strict_types=1);

namespace App\Auth;

use Google_Client;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * The real verifier: google/apiclient's verifyIdToken(), which checks the
 * signature against Google's published keys, the expiry, the audience and the
 * issuer.
 */
class GoogleTokenVerifier implements GoogleTokenVerifierInterface
{
    /**
     * @inheritDoc
     */
    public function verify(string $credential, string $clientId): ?array
    {
        $client = new Google_Client(['client_id' => $clientId]);

        try {
            $payload = $client->verifyIdToken($credential);
        } catch (UnexpectedValueException | InvalidArgumentException $e) {
            // php-jwt throws these for input that isn't a token (e.g. "Wrong
            // number of segments"). Failing to fetch Google's keys is a
            // different exception and still surfaces as a server error.
            throw new InvalidGoogleCredentialException('Malformed Google credential', 0, $e);
        }

        return is_array($payload) ? $payload : null;
    }
}

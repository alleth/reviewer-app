<?php
declare(strict_types=1);

namespace App\Auth;

use Google_Client;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use OutOfBoundsException;
use UnexpectedValueException;

/**
 * The real verifier: google/apiclient's verifyIdToken(), which checks the
 * signature against Google's published keys, the expiry, the audience and the
 * issuer.
 */
class GoogleTokenVerifier implements GoogleTokenVerifierInterface
{
    /**
     * @param \GuzzleHttp\ClientInterface|null $http HTTP client used to fetch
     *   Google's signing keys. Null uses google/apiclient's default; tests pass
     *   one that serves a fixed key set.
     */
    public function __construct(private ?ClientInterface $http = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function verify(string $credential, string $clientId): ?array
    {
        $client = new Google_Client(['client_id' => $clientId]);
        if ($this->http !== null) {
            $client->setHttpClient($this->http);
        }

        try {
            $payload = $client->verifyIdToken($credential);
        // php-jwt's CachedKeySet throws this from inside verifyIdToken(), which
        // doesn't declare it; GoogleTokenVerifierTest proves it happens.
        // @phpstan-ignore catch.neverThrown
        } catch (OutOfBoundsException $e) {
            // A well-formed token naming a signing key Google doesn't publish
            // (php-jwt: "Key ID not found"): forged or garbage, just not
            // malformed. Same answer as a bad signature — invalid, 401.
            return null;
        } catch (UnexpectedValueException | InvalidArgumentException $e) {
            // php-jwt throws these for input that isn't a token (e.g. "Wrong
            // number of segments"). Failing to fetch Google's keys is a
            // different exception and still surfaces as a server error.
            throw new InvalidGoogleCredentialException('Malformed Google credential', 0, $e);
        }

        return is_array($payload) ? $payload : null;
    }
}

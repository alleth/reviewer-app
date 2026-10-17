<?php
declare(strict_types=1);

namespace App\Auth;

/**
 * Verifies a Google Sign-In ID token (the `credential` the SPA posts to
 * /api/google-login). Registered in Application::services() and injected into
 * UsersController::googleLogin(), so tests can swap in a fake with
 * mockService() instead of needing a real, Google-signed token.
 */
interface GoogleTokenVerifierInterface
{
    /**
     * @param string $credential The ID token (a JWT) from Google.
     * @param string $clientId The OAuth client ID the token must be issued to.
     * @return array<string, mixed>|null The token's claims, or null when the
     *   token is well-formed but not valid (bad signature, expired, wrong
     *   audience or issuer).
     * @throws \App\Auth\InvalidGoogleCredentialException When the credential
     *   isn't a parseable token at all.
     */
    public function verify(string $credential, string $clientId): ?array;
}

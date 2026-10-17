<?php
declare(strict_types=1);

namespace App\Test\TestSuite;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;

/**
 * Lets tests run the real App\Auth\GoogleTokenVerifier without the network:
 * googleKeysHttp() is an HTTP client that answers every request with a fixed
 * Google-style key set (one RSA key, kid "test-key"), and fakeIdToken() builds
 * a well-formed but unsigned ID token naming whichever key id you choose.
 */
trait GoogleKeysTestTrait
{
    /**
     * Requests the fake key server has answered.
     */
    protected int $googleKeyFetches = 0;

    /**
     * @return \GuzzleHttp\Client
     */
    protected function googleKeysHttp(): Client
    {
        $jwks = json_encode(['keys' => [[
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => 'test-key',
            'e' => 'AQAB',
            'n' => '2sRW4qsQSxLBq4sOTk13XxDNhq-q5btE_8cpqKhy-E8G3EFJY-X1Bw9bjszcl6EhYTTt1EINKQ3bmlsu_03XzwMFUpnvhCTSp'
                . 'n-Rz8-Cb7wW0Uh0tS2iLW2OuihNnvmHo4ZVVGLiOkJ_oDoqedSKsN58Eun8xat_UxfyYjQN2aVXeKzckWOQDSHTFqxJ6Y_h3-Yz'
                . 'gFqQSoxeWoGQPauicYG7a2lCtU0f8H3nBqxk7HAHdZ7lqqnOlEzT7ydV9oj2Hinp11IKG3M5Q91lXF0_hEUx0qZsQ26v5Acvj8tBW5'
                . 'O-L-fnVnsYY4wg94eGjBZW2lc-UpwLM47mS08NKTSFSQ',
        ]]]);

        // A handler that never runs dry: a queue-based MockHandler throws
        // OutOfBoundsException when empty, which would hide the real one.
        $handler = function () use ($jwks) {
            $this->googleKeyFetches++;

            return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], (string)$jwks));
        };

        return new Client(['handler' => HandlerStack::create($handler)]);
    }

    /**
     * A structurally valid RS256 ID token with a junk signature.
     */
    protected function fakeIdToken(string $kid, string $audience = 'test-client-id'): string
    {
        $part = fn(array $data): string => rtrim(strtr(base64_encode((string)json_encode($data)), '+/', '-_'), '=');

        return $part(['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT']) . '.'
            . $part([
                'iss' => 'https://accounts.google.com',
                'aud' => $audience,
                'sub' => '1234567890',
                'email' => 'someone@example.com',
                'email_verified' => true,
                'iat' => time(),
                'exp' => time() + 3600,
            ]) . '.'
            . rtrim(strtr(base64_encode('not-a-real-signature'), '+/', '-_'), '=');
    }
}

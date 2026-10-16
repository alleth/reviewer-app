<?php
declare(strict_types=1);

namespace App\Test\TestCase\Auth;

use App\Auth\GoogleTokenVerifier;
use App\Auth\InvalidGoogleCredentialException;
use App\Test\TestSuite\GoogleKeysTestTrait;
use Cake\TestSuite\TestCase;

/**
 * The real Google verifier against a fake key server: every way a client can
 * send a bad credential must come back as "invalid" (null) or "malformed"
 * (InvalidGoogleCredentialException), never as an unexpected exception.
 *
 * @uses \App\Auth\GoogleTokenVerifier
 */
class GoogleTokenVerifierTest extends TestCase
{
    use GoogleKeysTestTrait;

    public function testTokenNamingAnUnknownSigningKeyIsInvalid(): void
    {
        $verifier = new GoogleTokenVerifier($this->googleKeysHttp());

        $this->assertNull($verifier->verify($this->fakeIdToken('no-such-key'), 'test-client-id'));
        $this->assertGreaterThan(0, $this->googleKeyFetches, 'The key set should have been fetched');
    }

    public function testTokenWithAKnownKeyButBadSignatureIsInvalid(): void
    {
        $verifier = new GoogleTokenVerifier($this->googleKeysHttp());

        $this->assertNull($verifier->verify($this->fakeIdToken('test-key'), 'test-client-id'));
        $this->assertGreaterThan(0, $this->googleKeyFetches);
    }

    public function testSomethingThatIsNotATokenIsMalformed(): void
    {
        $verifier = new GoogleTokenVerifier($this->googleKeysHttp());

        $this->expectException(InvalidGoogleCredentialException::class);
        $verifier->verify('not-a-jwt', 'test-client-id');
    }
}

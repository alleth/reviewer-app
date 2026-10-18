<?php
declare(strict_types=1);

namespace App\Test\TestCase\Security;

use App\Security\TurnstileVerifier;
use App\Security\TurnstileVerifierInterface;
use App\Test\TestSuite\ApiTestTrait;
use Cake\Http\Client;
use Cake\Http\TestSuite\HttpClientTrait;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * H4: Cloudflare Turnstile on register and forgot password. The real verifier
 * against a mocked siteverify endpoint, then the endpoints with a fake one:
 * refused without a valid token when configured, skipped when not, and let
 * through (logged) when Cloudflare can't be asked.
 *
 * @uses \App\Security\TurnstileVerifier
 * @uses \App\Controller\UsersController::register()
 * @uses \App\Controller\UsersController::forgotPassword()
 */
class TurnstileTest extends TestCase
{
    use ApiTestTrait;
    use HttpClientTrait;
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.AuthCodes',
        'app.RateLimits',
    ];

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnv('BREVO_API_KEY', '');
        $this->setEnv('EMAIL_TRANSPORT_DEFAULT_URL', '');
        $this->setEnv('TURNSTILE_SECRET_KEY', 'test-secret');
        Log::setConfig('test_log', ['className' => 'Array', 'levels' => ['warning']]);
    }

    protected function tearDown(): void
    {
        Log::drop('test_log');
        $this->restoreEnv();
        parent::tearDown();
    }

    /**
     * Swaps the verifier for one that answers $result.
     */
    private function fakeTurnstile(?bool $result, bool $enabled = true): void
    {
        $this->mockService(TurnstileVerifierInterface::class, function () use ($result, $enabled) {
            return new class ($result, $enabled) implements TurnstileVerifierInterface {
                public function __construct(private ?bool $result, private bool $enabled)
                {
                }

                public function isEnabled(): bool
                {
                    return $this->enabled;
                }

                public function verify(string $token, string $remoteIp): ?bool
                {
                    return $this->result;
                }
            };
        });
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function register(array $extra = []): void
    {
        $this->asAjax();
        $this->post('/api/register', $extra + [
            'fname' => 'New',
            'lname' => 'Person',
            'email' => 'new@example.org',
            'user_name' => 'newperson',
            'user_pass' => 'Password123',
        ]);
    }

    /**
     * @return list<string>
     */
    private function logged(): array
    {
        $engine = Log::engine('test_log');
        $this->assertInstanceOf(ArrayLog::class, $engine);

        return $engine->read();
    }

    // ---- the real verifier -----------------------------------------------

    public function testTheVerifierIsOnlyEnabledWithASecret(): void
    {
        $this->assertTrue((new TurnstileVerifier())->isEnabled());

        $this->setEnv('TURNSTILE_SECRET_KEY', '  ');
        $this->assertFalse((new TurnstileVerifier())->isEnabled());
    }

    public function testTheVerifierReadsCloudflaresAnswer(): void
    {
        $this->mockClientPost(self::VERIFY_URL, $this->newClientResponse(
            200,
            ['Content-Type: application/json'],
            (string)json_encode(['success' => true]),
        ));
        $this->assertTrue((new TurnstileVerifier())->verify('token', '198.51.100.20'));

        Client::clearMockResponses();
        $this->mockClientPost(self::VERIFY_URL, $this->newClientResponse(
            200,
            ['Content-Type: application/json'],
            (string)json_encode(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ));
        $this->assertFalse((new TurnstileVerifier())->verify('bad-token', '198.51.100.20'));
    }

    public function testTheVerifierReportsUnavailableOnAnErrorOrNoAnswer(): void
    {
        $this->mockClientPost(self::VERIFY_URL, $this->newClientResponse(503));
        $this->assertNull((new TurnstileVerifier())->verify('token', '198.51.100.20'));

        Client::clearMockResponses();
        // Only some other URL is mocked: the request to Cloudflare can't be
        // made at all, the same as Cloudflare being unreachable.
        $this->mockClientPost('https://example.invalid/other', $this->newClientResponse(200));
        $this->assertNull((new TurnstileVerifier())->verify('token', '198.51.100.20'));
    }

    // ---- the endpoints ---------------------------------------------------

    public function testRegisterNeedsAValidToken(): void
    {
        $this->fakeTurnstile(false);

        $this->register();
        $this->assertResponseCode(400);
        $this->assertSame('Please complete the security check and try again.', $this->responseJson()['message']);

        $this->register(['turnstile_token' => 'bad']);
        $this->assertResponseCode(400);
        $this->assertSame(0, $this->getTableLocator()->get('Users')->find()->where(['user_name' => 'newperson'])->count());
    }

    public function testRegisterWithAValidTokenGoesThrough(): void
    {
        $this->fakeTurnstile(true);

        $this->register(['turnstile_token' => 'good']);

        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['needsVerification']);
    }

    public function testCloudflareBeingUnavailableFailsOpenAndIsLogged(): void
    {
        $this->fakeTurnstile(null);

        $this->register(['turnstile_token' => 'any']);

        $this->assertTrue($this->responseJson()['needsVerification'] ?? false);
        $this->assertNotEmpty(array_filter(
            $this->logged(),
            fn(string $line): bool => str_contains($line, '[turnstile] verification unavailable; allowed (fail open)'),
        ));
    }

    public function testWithoutASecretTheCheckIsSkipped(): void
    {
        $this->fakeTurnstile(false, false);

        $this->register();

        $this->assertTrue($this->responseJson()['needsVerification'] ?? false);
    }

    public function testForgotPasswordNeedsAValidTokenToo(): void
    {
        $this->fakeTurnstile(false);
        $this->asAjax();
        $this->post('/api/password/forgot', ['email' => 'jane@example.com']);
        $this->assertResponseCode(400);

        $this->fakeTurnstile(true);
        $this->asAjax();
        $this->post('/api/password/forgot', ['email' => 'jane@example.com', 'turnstile_token' => 'good']);
        $this->assertResponseOk();
        $this->assertSame('If that email has an account, a reset code is on its way.', $this->responseJson()['message']);
    }
}

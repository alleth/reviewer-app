<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Auth\GoogleTokenVerifier;
use App\Auth\GoogleTokenVerifierInterface;
use App\Auth\InvalidGoogleCredentialException;
use App\Model\Entity\User;
use App\Test\TestSuite\ApiTestTrait;
use App\Test\TestSuite\GoogleKeysTestTrait;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * B3-V: email verification ("no session until verified") and the Google
 * pre-hijacking fixes.
 *
 * Mail has no transport here, so sendMail() logs each message; the test reads
 * the emailed codes back from an ArrayLog. Google ID tokens are checked by a
 * fake GoogleTokenVerifierInterface swapped in with mockService().
 *
 * @uses \App\Controller\UsersController
 */
class UsersVerificationTest extends TestCase
{
    use ApiTestTrait;
    use GoogleKeysTestTrait;
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.AuthCodes',
        'app.LoginEvents',
        'app.LoginAttempts',
        'app.Passes',
    ];

    private const PASSWORD = 'Password123';

    protected function setUp(): void
    {
        parent::setUp();
        // Force the log fallback even if the developer's env has a mail transport.
        $this->setEnv('BREVO_API_KEY', '');
        $this->setEnv('EMAIL_TRANSPORT_DEFAULT_URL', '');
        $this->setEnv('GOOGLE_CLIENT_ID', 'test-client-id');
        Log::setConfig('test_mail', ['className' => 'Array', 'levels' => ['warning']]);
    }

    protected function tearDown(): void
    {
        Log::drop('test_mail');
        $this->restoreEnv();
        parent::tearDown();
    }

    /**
     * POSTs the way the SPA does. Request config is reset after every request,
     * so the AJAX header is set each time.
     *
     * @param array<string, mixed> $data
     */
    private function ajaxPost(string $url, array $data): void
    {
        $this->asAjax();
        $this->post($url, $data);
    }

    /**
     * Codes emailed so far, oldest first, to $email.
     *
     * @return list<string>
     */
    private function emailedCodes(string $email): array
    {
        $engine = Log::engine('test_mail');
        $this->assertInstanceOf(ArrayLog::class, $engine);
        $codes = [];
        foreach ($engine->read() as $line) {
            if (str_contains($line, "to $email |") && preg_match('/Your code is: (\d{6})/', $line, $m)) {
                $codes[] = $m[1];
            }
        }

        return $codes;
    }

    private function lastCode(string $email): string
    {
        $codes = $this->emailedCodes($email);
        $this->assertNotEmpty($codes, "No code was emailed to $email");

        return (string)end($codes);
    }

    private function users(): Table
    {
        return $this->getTableLocator()->get('Users');
    }

    private function user(int $id): User
    {
        /** @var \App\Model\Entity\User $user */
        $user = $this->users()->get($id);

        return $user;
    }

    /**
     * Inserts an account directly, unverified unless $verified.
     */
    private function createUser(string $email, bool $verified = false, ?string $googleId = null): User
    {
        $user = $this->users()->newEntity([
            'fname' => 'Sam',
            'lname' => 'Squatter',
            'email' => $email,
            'user_name' => strstr($email, '@', true),
        ]);
        $user->user_pass = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $user->google_id = $googleId;
        $user->email_verified_at = $verified ? new DateTime('2026-10-01 00:00:00') : null;
        $this->users()->saveOrFail($user);
        /** @var \App\Model\Entity\User $user */
        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function register(array $overrides = []): void
    {
        $this->ajaxPost('/api/register', $overrides + [
            'fname' => 'Juan',
            'lname' => 'Santos',
            'email' => 'juan@example.com',
            'user_name' => 'juansantos',
            'user_pass' => self::PASSWORD,
        ]);
    }

    /**
     * Swaps the Google verifier for one that returns $payload (or throws).
     *
     * @param array<string, mixed>|null $payload Claims, or null for an invalid token.
     */
    private function fakeGoogle(?array $payload, bool $malformed = false): void
    {
        $this->mockService(GoogleTokenVerifierInterface::class, function () use ($payload, $malformed) {
            return new class ($payload, $malformed) implements GoogleTokenVerifierInterface {
                /**
                 * @param array<string, mixed>|null $payload
                 */
                public function __construct(private ?array $payload, private bool $malformed)
                {
                }

                public function verify(string $credential, string $clientId): ?array
                {
                    if ($this->malformed) {
                        throw new InvalidGoogleCredentialException('malformed');
                    }

                    return $this->payload;
                }
            };
        });
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function googleClaims(string $email, array $overrides = []): array
    {
        return $overrides + [
            'sub' => 'google-sub-123',
            'email' => $email,
            'email_verified' => true,
            'given_name' => 'Real',
            'family_name' => 'Owner',
        ];
    }

    private function countCodes(int $userId, ?string $purpose = null): int
    {
        $conditions = ['user_id' => $userId];
        if ($purpose !== null) {
            $conditions['purpose'] = $purpose;
        }

        return $this->getTableLocator()->get('AuthCodes')->find()->where($conditions)->count();
    }

    /** Ages every "code sent" marker for $userId, as if $seconds had passed. */
    private function ageSentMarkers(int $userId, int $seconds): void
    {
        $this->getTableLocator()->get('AuthCodes')->updateAll(
            ['created' => (new DateTime())->modify("-$seconds seconds")],
            ['user_id' => $userId, 'purpose' => 'verify_email_sent'],
        );
    }

    // ---- register -------------------------------------------------------

    public function testRegisterCreatesUnverifiedAccountWithoutSession(): void
    {
        $this->register();

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertFalse($body['success']);
        $this->assertTrue($body['needsVerification']);
        $this->assertSame('juan@example.com', $body['email']);
        $this->assertArrayNotHasKey('user', $body);
        $this->assertSessionNotHasKey('Auth.User');

        $user = $this->users()->find()->where(['email' => 'juan@example.com'])->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame(1, $this->countCodes((int)$user->user_id, 'verify_email'));
        $this->assertCount(1, $this->emailedCodes('juan@example.com'));
    }

    public function testRegisterIgnoresProtectedFields(): void
    {
        $this->register([
            'google_id' => 'attacker-google-sub',
            'email_verified_at' => '2026-01-01 00:00:00',
            'session_token' => 'attacker-token',
        ]);

        $user = $this->users()->find()->where(['email' => 'juan@example.com'])->firstOrFail();
        $this->assertNull($user->google_id);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->session_token);
        $this->assertTrue(password_verify(self::PASSWORD, (string)$user->user_pass));
    }

    // ---- verify ---------------------------------------------------------

    public function testCorrectCodeVerifiesAndStartsSession(): void
    {
        $this->register();
        $code = $this->lastCode('juan@example.com');

        $this->ajaxPost('/api/verify-email', ['email' => 'juan@example.com', 'code' => $code]);

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertTrue($body['success']);
        $this->assertSame('juansantos', $body['user']['user_name']);
        $this->assertArrayNotHasKey('user_pass', $body['user']);

        $user = $this->users()->find()->where(['email' => 'juan@example.com'])->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->session_token);
        $this->assertSession($user->session_token, 'Auth.token');
        $this->assertSame(0, $this->countCodes((int)$user->user_id, 'verify_email'));
    }

    public function testWrongCodeIsRejectedAndFifthWrongCodeKillsIt(): void
    {
        $this->register();
        $code = $this->lastCode('juan@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';
        $userId = (int)$this->users()->find()->where(['email' => 'juan@example.com'])->firstOrFail()->user_id;

        for ($i = 1; $i <= 5; $i++) {
            $this->ajaxPost('/api/verify-email', ['email' => 'juan@example.com', 'code' => $wrong]);
            $this->assertResponseCode(400);
            $this->assertSessionNotHasKey('Auth.User');
        }
        $this->assertSame(0, $this->countCodes($userId, 'verify_email'));

        // The real code no longer works either.
        $this->ajaxPost('/api/verify-email', ['email' => 'juan@example.com', 'code' => $code]);
        $this->assertResponseCode(400);
        $this->assertNull($this->user($userId)->email_verified_at);
    }

    public function testExpiredCodeIsRejected(): void
    {
        $this->register();
        $code = $this->lastCode('juan@example.com');
        $this->getTableLocator()->get('AuthCodes')->updateAll(
            ['expires' => (new DateTime())->modify('-1 minute')],
            ['purpose' => 'verify_email'],
        );

        $this->ajaxPost('/api/verify-email', ['email' => 'juan@example.com', 'code' => $code]);

        $this->assertResponseCode(400);
        $this->assertSessionNotHasKey('Auth.User');
    }

    public function testUnknownEmailAndVerifiedAccountGetTheSameAnswer(): void
    {
        $this->ajaxPost('/api/verify-email', ['email' => 'nobody@example.com', 'code' => '123456']);
        $this->assertResponseCode(400);
        $unknown = $this->responseJson();

        // jane@example.com (fixture user 1) is already verified.
        $this->ajaxPost('/api/verify-email', ['email' => 'jane@example.com', 'code' => '123456']);
        $this->assertResponseCode(400);
        $this->assertSame($unknown, $this->responseJson());
        $this->assertSessionNotHasKey('Auth.User');
    }

    public function testVerifyRequiresAjaxHeader(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/api/verify-email', ['email' => 'juan@example.com', 'code' => '123456']);

        $this->assertResponseCode(403);
    }

    // ---- resend ---------------------------------------------------------

    public function testResendWithinCooldownSendsNothing(): void
    {
        $this->register();

        $this->ajaxPost('/api/verify-email/resend', ['email' => 'juan@example.com']);

        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['success']);
        $this->assertCount(1, $this->emailedCodes('juan@example.com'));
    }

    public function testResendAfterCooldownReplacesTheCode(): void
    {
        $this->register();
        $first = $this->lastCode('juan@example.com');
        $userId = (int)$this->users()->find()->where(['email' => 'juan@example.com'])->firstOrFail()->user_id;
        $this->ageSentMarkers($userId, 61);

        $this->ajaxPost('/api/verify-email/resend', ['email' => 'juan@example.com']);

        $this->assertResponseOk();
        $this->assertCount(2, $this->emailedCodes('juan@example.com'));
        $second = $this->lastCode('juan@example.com');
        $this->assertSame(1, $this->countCodes($userId, 'verify_email'));

        if ($first !== $second) {
            $this->ajaxPost('/api/verify-email', ['email' => 'juan@example.com', 'code' => $first]);
            $this->assertResponseCode(400);
        }
        $this->ajaxPost('/api/verify-email', ['email' => 'juan@example.com', 'code' => $second]);
        $this->assertResponseOk();
    }

    public function testResendStopsAtTheDailyCap(): void
    {
        $user = $this->createUser('capped@example.com');
        $codes = $this->getTableLocator()->get('AuthCodes');
        for ($i = 0; $i < 10; $i++) {
            $codes->saveOrFail($codes->newEntity([
                'user_id' => $user->user_id,
                'purpose' => 'verify_email_sent',
                'code_hash' => '',
                'expires' => (new DateTime())->modify('+1 day'),
                'attempts' => 0,
                'created' => (new DateTime())->modify('-' . (2 + $i) . ' hours'),
            ]));
        }

        $this->ajaxPost('/api/verify-email/resend', ['email' => 'capped@example.com']);
        $this->assertResponseOk();
        $this->assertCount(0, $this->emailedCodes('capped@example.com'));

        // Markers older than a day stop counting.
        $codes->updateAll(
            ['created' => (new DateTime())->modify('-25 hours')],
            ['user_id' => $user->user_id, 'purpose' => 'verify_email_sent'],
        );
        $this->ajaxPost('/api/verify-email/resend', ['email' => 'capped@example.com']);
        $this->assertCount(1, $this->emailedCodes('capped@example.com'));
        $this->assertSame(1, $this->countCodes((int)$user->user_id, 'verify_email_sent'));
    }

    public function testResendGivesTheSameAnswerForUnknownAndVerifiedEmails(): void
    {
        $this->ajaxPost('/api/verify-email/resend', ['email' => 'nobody@example.com']);
        $unknown = $this->responseJson();
        $this->assertResponseOk();

        $this->ajaxPost('/api/verify-email/resend', ['email' => 'jane@example.com']);
        $this->assertResponseOk();
        $this->assertSame($unknown, $this->responseJson());
        $this->assertCount(0, $this->emailedCodes('jane@example.com'));
    }

    // ---- login ----------------------------------------------------------

    public function testCorrectPasswordOnUnverifiedAccountAsksForCodeInsteadOfSignIn(): void
    {
        $user = $this->createUser('pending@example.com');

        $this->ajaxPost('/api/login', ['user_name' => 'pending', 'user_pass' => self::PASSWORD]);

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertFalse($body['success']);
        $this->assertTrue($body['needsVerification']);
        $this->assertSame('pending@example.com', $body['email']);
        $this->assertSessionNotHasKey('Auth.User');
        $this->assertNull($this->user((int)$user->user_id)->session_token);
        $this->assertCount(1, $this->emailedCodes('pending@example.com'));

        // A second attempt inside the cooldown doesn't send another email.
        $this->ajaxPost('/api/login', ['user_name' => 'pending', 'user_pass' => self::PASSWORD]);
        $this->assertTrue($this->responseJson()['needsVerification']);
        $this->assertCount(1, $this->emailedCodes('pending@example.com'));

        // The emailed code finishes the sign-in.
        $this->ajaxPost('/api/verify-email', [
            'email' => 'pending@example.com',
            'code' => $this->lastCode('pending@example.com'),
        ]);
        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['success']);
    }

    public function testWrongPasswordOnUnverifiedAccountIsANormalFailure(): void
    {
        $this->createUser('pending@example.com');

        $this->ajaxPost('/api/login', ['user_name' => 'pending', 'user_pass' => 'wrong-password']);

        $body = $this->responseJson();
        $this->assertFalse($body['success']);
        $this->assertArrayNotHasKey('needsVerification', $body);
        $this->assertCount(0, $this->emailedCodes('pending@example.com'));
        $this->assertSame(
            1,
            $this->getTableLocator()->get('LoginAttempts')->find()->where(['identifier' => 'pending'])->count(),
        );
    }

    // ---- password reset -------------------------------------------------

    public function testPasswordResetVerifiesTheAccount(): void
    {
        $user = $this->createUser('pending@example.com');

        $this->ajaxPost('/api/password/forgot', ['email' => 'pending@example.com']);
        $this->ajaxPost('/api/password/reset', [
            'email' => 'pending@example.com',
            'code' => $this->lastCode('pending@example.com'),
            'password' => 'NewPassword456',
        ]);
        $this->assertResponseOk();
        $this->assertNotNull($this->user((int)$user->user_id)->email_verified_at);

        $this->ajaxPost('/api/login', ['user_name' => 'pending', 'user_pass' => 'NewPassword456']);
        $this->assertTrue($this->responseJson()['success']);
    }

    // ---- Google ---------------------------------------------------------

    public function testGoogleLoginRejectsAnEmailGoogleHasNotVerified(): void
    {
        foreach ([false, 'true', null] as $flag) {
            $claims = $this->googleClaims('new@example.com', ['email_verified' => $flag]);
            if ($flag === null) {
                unset($claims['email_verified']);
            }
            $this->fakeGoogle($claims);

            $this->ajaxPost('/api/google-login', ['credential' => 'token']);

            $this->assertResponseCode(403);
            $this->assertSessionNotHasKey('Auth.User');
        }
        $this->assertSame(0, $this->users()->find()->where(['email' => 'new@example.com'])->count());
    }

    public function testGoogleLoginRejectsATokenWithoutEmail(): void
    {
        $this->fakeGoogle($this->googleClaims('', ['email' => '']));

        $this->ajaxPost('/api/google-login', ['credential' => 'token']);

        $this->assertResponseCode(403);
        $this->assertSessionNotHasKey('Auth.User');
    }

    public function testGoogleSignUpCreatesAVerifiedAccount(): void
    {
        $this->fakeGoogle($this->googleClaims('new@example.com'));

        $this->ajaxPost('/api/google-login', ['credential' => 'token']);

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertTrue($body['success']);
        $this->assertTrue($body['isNewUser']);
        $user = $this->users()->find()->where(['email' => 'new@example.com'])->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('google-sub-123', $user->google_id);
        $this->assertSession($user->session_token, 'Auth.token');
    }

    public function testGoogleTakesOverAnUnverifiedAccount(): void
    {
        $squatted = $this->createUser('victim@example.com');
        $userId = (int)$squatted->user_id;
        $codes = $this->getTableLocator()->get('AuthCodes');
        $codes->saveOrFail($codes->newEntity([
            'user_id' => $userId,
            'purpose' => 'verify_email',
            'code_hash' => password_hash('123456', PASSWORD_DEFAULT),
            'expires' => (new DateTime())->modify('+10 minutes'),
            'attempts' => 0,
            'created' => new DateTime(),
        ]));
        $events = $this->getTableLocator()->get('LoginEvents');
        $events->saveOrFail($events->newEntity([
            'user_id' => $userId,
            'device_hash' => 'squatter-device',
            'created' => new DateTime(),
        ]));
        $this->fakeGoogle($this->googleClaims('victim@example.com'));

        $this->ajaxPost('/api/google-login', ['credential' => 'token']);

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertTrue($body['success']);
        $this->assertTrue($body['isNewUser'], 'The owner should be offered account setup');

        $user = $this->user($userId);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->user_pass, "The squatter's password must be gone");
        $this->assertSame('google-sub-123', $user->google_id);
        $this->assertSame('Real', $user->fname);
        $this->assertSame('Owner', $user->lname);
        $this->assertSame('victim', $user->user_name, 'user_name is kept');
        $this->assertSession($user->session_token, 'Auth.token');
        $this->assertSame(0, $this->countCodes($userId));
        $this->assertSame(0, $events->find()->where(['device_hash' => 'squatter-device'])->count());

        // The squatter's password no longer signs in.
        $this->ajaxPost('/api/login', ['user_name' => 'victim', 'user_pass' => self::PASSWORD]);
        $this->assertFalse($this->responseJson()['success']);
        $this->assertArrayNotHasKey('needsVerification', $this->responseJson());
    }

    public function testTakeoverKeepsNamesGoogleDoesNotProvide(): void
    {
        $squatted = $this->createUser('victim@example.com');
        $this->fakeGoogle($this->googleClaims('victim@example.com', ['given_name' => '', 'family_name' => '']));

        $this->ajaxPost('/api/google-login', ['credential' => 'token']);

        $this->assertResponseOk();
        $user = $this->user((int)$squatted->user_id);
        $this->assertSame('Sam', $user->fname);
        $this->assertSame('Squatter', $user->lname);
        $this->assertNotNull($user->email_verified_at);
    }

    public function testTakeoverRollsBackCompletelyWhenTheSaveFails(): void
    {
        $squatted = $this->createUser('victim@example.com');
        $userId = (int)$squatted->user_id;
        $codes = $this->getTableLocator()->get('AuthCodes');
        $codes->saveOrFail($codes->newEntity([
            'user_id' => $userId,
            'purpose' => 'verify_email',
            'code_hash' => password_hash('123456', PASSWORD_DEFAULT),
            'expires' => (new DateTime())->modify('+10 minutes'),
            'attempts' => 0,
            'created' => new DateTime(),
        ]));
        $events = $this->getTableLocator()->get('LoginEvents');
        $events->saveOrFail($events->newEntity([
            'user_id' => $userId,
            'device_hash' => 'squatter-device',
            'created' => new DateTime(),
        ]));
        // Make the user save inside the takeover fail, after the deletes ran.
        $this->users()->getEventManager()->on('Model.beforeSave', function (EventInterface $event): void {
            $event->stopPropagation();
            $event->setResult(false);
        });
        $this->fakeGoogle($this->googleClaims('victim@example.com'));

        $this->ajaxPost('/api/google-login', ['credential' => 'token']);

        $this->assertResponseCode(500);
        $this->assertSessionNotHasKey('Auth.User');
        $user = $this->user($userId);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->google_id);
        $this->assertNull($user->session_token);
        $this->assertTrue(password_verify(self::PASSWORD, (string)$user->user_pass));
        $this->assertSame('Sam', $user->fname);
        $this->assertSame(1, $this->countCodes($userId, 'verify_email'), 'Deleted codes must be rolled back');
        $this->assertSame(1, $events->find()->where(['device_hash' => 'squatter-device'])->count());
    }

    public function testGoogleLinksAVerifiedAccountAsBefore(): void
    {
        // jane@example.com (fixture user 1) is verified and has a password.
        $this->fakeGoogle($this->googleClaims('jane@example.com'));

        $this->ajaxPost('/api/google-login', ['credential' => 'token']);

        $this->assertResponseOk();
        $this->assertFalse($this->responseJson()['isNewUser']);
        $user = $this->user(1);
        $this->assertSame('google-sub-123', $user->google_id);
        $this->assertTrue(password_verify(self::PASSWORD, (string)$user->user_pass));
        $this->assertSame('Jane', $user->fname);
    }

    public function testInvalidGoogleTokenIs401(): void
    {
        $this->fakeGoogle(null);

        $this->ajaxPost('/api/google-login', ['credential' => 'token']);

        $this->assertResponseCode(401);
    }

    public function testTokenSignedByAnUnknownKeyIs401WithoutAnErrorLog(): void
    {
        // The real verifier, with Google's key set served locally. Staging
        // returned a 500 (and logged a stack trace) for this before.
        $http = $this->googleKeysHttp();
        $this->mockService(GoogleTokenVerifierInterface::class, fn() => new GoogleTokenVerifier($http));
        Log::setConfig('test_errors', ['className' => 'Array', 'levels' => ['error']]);

        try {
            $this->ajaxPost('/api/google-login', ['credential' => $this->fakeIdToken('no-such-key')]);

            $this->assertResponseCode(401);
            $this->assertSame('Invalid Google token', $this->responseJson()['message']);
            $this->assertSessionNotHasKey('Auth.User');
            $engine = Log::engine('test_errors');
            $this->assertInstanceOf(ArrayLog::class, $engine);
            $this->assertSame([], $engine->read(), 'An invalid token is not a server error');
        } finally {
            Log::drop('test_errors');
        }
    }

    public function testMalformedGoogleCredentialIs400(): void
    {
        $this->fakeGoogle(null, true);
        $this->ajaxPost('/api/google-login', ['credential' => 'token']);
        $this->assertResponseCode(400);

        // And through the real verifier: not a JWT at all, rejected before any key fetch.
        $this->removeMockService(GoogleTokenVerifierInterface::class);
        $this->ajaxPost('/api/google-login', ['credential' => 'not-a-jwt']);
        $this->assertResponseCode(400);
        $this->assertSame('Invalid Google credential', $this->responseJson()['message']);
    }

    public function testSessionIsNeverStartedForAnUnverifiedAccount(): void
    {
        // Can't happen through the API any more; the startSession() backstop
        // still refuses if some path ever gets here.
        $this->createUser('linked@example.com', false, 'google-sub-123');
        $this->fakeGoogle($this->googleClaims('linked@example.com'));

        $this->ajaxPost('/api/google-login', ['credential' => 'token']);

        $this->assertResponseCode(500);
        $this->assertSessionNotHasKey('Auth.User');
    }
}

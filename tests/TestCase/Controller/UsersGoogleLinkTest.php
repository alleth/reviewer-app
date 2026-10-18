<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Auth\GoogleTokenVerifierInterface;
use App\Model\Entity\User;
use App\Test\TestSuite\ApiTestTrait;
use Cake\I18n\DateTime;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * B3-L: a Google sign-in whose email matches an existing *verified* account
 * that isn't linked to that Google account is linked only after an emailed
 * code. A match on an account linked to a *different* Google account is
 * refused (409). Everything else in googleLogin() is unchanged.
 *
 * Fixture user 1 (jane@example.com) is verified, has a password and no
 * google_id — the case B3-L is about.
 *
 * @uses \App\Controller\UsersController::googleLogin()
 */
class UsersGoogleLinkTest extends TestCase
{
    use ApiTestTrait;
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.AuthCodes',
        'app.LoginEvents',
        'app.LoginAttempts',
        'app.RateLimits',
        'app.Passes',
    ];

    private const PASSWORD = 'Password123';
    private const GOOGLE_SUB = 'google-sub-jane';

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
     * Swaps the Google verifier for one that returns these claims.
     *
     * @param array<string, mixed> $overrides
     */
    private function fakeGoogle(array $overrides = []): void
    {
        $claims = $overrides + [
            'sub' => self::GOOGLE_SUB,
            'email' => 'jane@example.com',
            'email_verified' => true,
            'given_name' => 'Jane',
            'family_name' => 'Dela Cruz',
        ];
        $this->mockService(GoogleTokenVerifierInterface::class, function () use ($claims) {
            return new class ($claims) implements GoogleTokenVerifierInterface {
                /**
                 * @param array<string, mixed> $claims
                 */
                public function __construct(private array $claims)
                {
                }

                public function verify(string $credential, string $clientId): ?array
                {
                    return $this->claims;
                }
            };
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    private function googleLogin(array $data = []): void
    {
        $this->asAjax();
        $this->post('/api/google-login', $data + ['credential' => 'token']);
    }

    /**
     * Emails logged so far (no mail transport in tests), as "subject | body".
     *
     * @return list<string>
     */
    private function mailTo(string $email): array
    {
        $engine = Log::engine('test_mail');
        $this->assertInstanceOf(ArrayLog::class, $engine);

        return array_values(array_filter(
            $engine->read(),
            fn(string $line): bool => str_contains($line, "[mail] no transport - to $email |"),
        ));
    }

    private function lastCode(string $email): string
    {
        $codes = [];
        foreach ($this->mailTo($email) as $line) {
            if (preg_match('/Your code is: (\d{6})/', $line, $m)) {
                $codes[] = $m[1];
            }
        }
        $this->assertNotEmpty($codes, "No code was emailed to $email");

        return (string)end($codes);
    }

    private function users(): Table
    {
        return $this->getTableLocator()->get('Users');
    }

    private function jane(): User
    {
        /** @var \App\Model\Entity\User $user */
        $user = $this->users()->get(1);

        return $user;
    }

    private function countCodes(string $purpose): int
    {
        return $this->getTableLocator()->get('AuthCodes')->find()
            ->where(['user_id' => 1, 'purpose' => $purpose])->count();
    }

    public function testFirstGoogleSignInAsksForACodeAndChangesNothing(): void
    {
        $this->fakeGoogle();

        $this->googleLogin();

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertFalse($body['success']);
        $this->assertTrue($body['needsLinkCode']);
        $this->assertSame('jane@example.com', $body['email']);
        $this->assertSessionNotHasKey('Auth.User');
        $this->assertNull($this->jane()->google_id);
        $this->assertNull($this->jane()->session_token);
        $this->assertSame(1, $this->countCodes('link_google'));
        $this->assertCount(1, $this->mailTo('jane@example.com'));
        $this->assertStringContainsString('connecting Google sign-in', $this->mailTo('jane@example.com')[0]);
    }

    public function testTheRightCodeLinksSignsInAndSendsANotice(): void
    {
        $this->fakeGoogle();
        $this->googleLogin();
        $code = $this->lastCode('jane@example.com');

        $this->googleLogin(['code' => $code]);

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertTrue($body['success']);
        $this->assertFalse($body['isNewUser']);
        $jane = $this->jane();
        $this->assertSame(self::GOOGLE_SUB, $jane->google_id);
        $this->assertTrue(password_verify(self::PASSWORD, (string)$jane->user_pass), 'The password is kept');
        $this->assertSession($jane->session_token, 'Auth.token');
        $this->assertSame(0, $this->countCodes('link_google'));
        $mail = $this->mailTo('jane@example.com');
        $this->assertCount(2, $mail);
        $this->assertStringContainsString('Google sign-in was added to your CareerPass account', $mail[1]);

        // From now on Google signs straight in: found by google_id, no code.
        $this->googleLogin();
        $this->assertTrue($this->responseJson()['success']);
        $this->assertCount(2, $this->mailTo('jane@example.com'));
    }

    public function testAWrongCodeIsRefusedAndTheFifthKillsIt(): void
    {
        $this->fakeGoogle();
        $this->googleLogin();
        $code = $this->lastCode('jane@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 5; $i++) {
            $this->googleLogin(['code' => $wrong]);
            $this->assertResponseCode(400);
            $this->assertSessionNotHasKey('Auth.User');
        }
        $this->assertSame(0, $this->countCodes('link_google'));

        $this->googleLogin(['code' => $code]);
        $this->assertResponseCode(400);
        $this->assertNull($this->jane()->google_id);
    }

    public function testAnExpiredCodeIsRefused(): void
    {
        $this->fakeGoogle();
        $this->googleLogin();
        $code = $this->lastCode('jane@example.com');
        $this->getTableLocator()->get('AuthCodes')->updateAll(
            ['expires' => (new DateTime())->modify('-1 minute')],
            ['purpose' => 'link_google'],
        );

        $this->googleLogin(['code' => $code]);

        $this->assertResponseCode(400);
        $this->assertNull($this->jane()->google_id);
    }

    public function testResendingRespectsTheCooldownAndReplacesTheCode(): void
    {
        $this->fakeGoogle();
        $this->googleLogin();
        $first = $this->lastCode('jane@example.com');

        // Inside the cooldown: the same answer, but no second email.
        $this->googleLogin();
        $this->assertTrue($this->responseJson()['needsLinkCode']);
        $this->assertCount(1, $this->mailTo('jane@example.com'));

        // After the cooldown: a new code, and the old one stops working.
        $this->getTableLocator()->get('AuthCodes')->updateAll(
            ['created' => (new DateTime())->modify('-61 seconds')],
            ['purpose' => 'link_google_sent'],
        );
        $this->googleLogin();
        $this->assertCount(2, $this->mailTo('jane@example.com'));
        $second = $this->lastCode('jane@example.com');
        if ($first !== $second) {
            $this->googleLogin(['code' => $first]);
            $this->assertResponseCode(400);
        }
        $this->googleLogin(['code' => $second]);
        $this->assertTrue($this->responseJson()['success']);
    }

    public function testTheDailyCapStopsFurtherCodes(): void
    {
        $codes = $this->getTableLocator()->get('AuthCodes');
        for ($i = 0; $i < 10; $i++) {
            $codes->saveOrFail($codes->newEntity([
                'user_id' => 1,
                'purpose' => 'link_google_sent',
                'code_hash' => '',
                'expires' => (new DateTime())->modify('+1 day'),
                'attempts' => 0,
                'created' => (new DateTime())->modify('-' . (2 + $i) . ' hours'),
            ]));
        }
        $this->fakeGoogle();

        $this->googleLogin();

        $this->assertTrue($this->responseJson()['needsLinkCode']);
        $this->assertCount(0, $this->mailTo('jane@example.com'));
        $this->assertSame(0, $this->countCodes('link_google'));
    }

    public function testLinkCodesAndVerificationCodesHaveSeparateLimits(): void
    {
        // A verification email just went out (its own cooldown); a link code
        // can still be sent.
        $codes = $this->getTableLocator()->get('AuthCodes');
        $codes->saveOrFail($codes->newEntity([
            'user_id' => 1,
            'purpose' => 'verify_email_sent',
            'code_hash' => '',
            'expires' => (new DateTime())->modify('+1 day'),
            'attempts' => 0,
            'created' => new DateTime(),
        ]));
        $this->fakeGoogle();

        $this->googleLogin();

        $this->assertCount(1, $this->mailTo('jane@example.com'));
    }

    public function testAnAccountLinkedToADifferentGoogleAccountIsNeverRelinked(): void
    {
        $users = $this->users();
        $jane = $this->jane();
        $jane->google_id = 'some-other-google-sub';
        $users->saveOrFail($jane);
        $this->fakeGoogle();

        $this->googleLogin();

        $this->assertResponseCode(409);
        $this->assertStringContainsString('different Google account', $this->responseJson()['message']);
        $this->assertSessionNotHasKey('Auth.User');
        $this->assertSame('some-other-google-sub', $this->jane()->google_id);
        $this->assertCount(0, $this->mailTo('jane@example.com'), 'No code email');
        $this->assertSame(0, $this->countCodes('link_google'));

        // A code (even a guessed one) doesn't change that.
        $this->googleLogin(['code' => '123456']);
        $this->assertResponseCode(409);
        $this->assertSame('some-other-google-sub', $this->jane()->google_id);
    }

    public function testACodeOnlyWorksForTheAccountItWasSentFor(): void
    {
        // A second verified, unlinked account.
        $users = $this->users();
        $other = $users->newEntity([
            'fname' => 'Other',
            'lname' => 'Person',
            'email' => 'other@example.com',
            'user_name' => 'otherperson',
        ]);
        $other->user_pass = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $other->email_verified_at = new DateTime('2026-10-01 00:00:00');
        $users->saveOrFail($other);

        $this->fakeGoogle();
        $this->googleLogin();
        $janesCode = $this->lastCode('jane@example.com');

        // A Google token for the other address can't use Jane's code.
        $this->fakeGoogle(['sub' => 'google-sub-other', 'email' => 'other@example.com']);
        $this->googleLogin(['code' => $janesCode]);

        $this->assertResponseCode(400);
        /** @var \App\Model\Entity\User $reloaded */
        $reloaded = $users->get($other->user_id);
        $this->assertNull($reloaded->google_id);
        $this->assertNull($this->jane()->google_id);
    }

    public function testAnAlreadyLinkedAccountSignsStraightIn(): void
    {
        $users = $this->users();
        $jane = $this->jane();
        $jane->google_id = self::GOOGLE_SUB;
        $users->saveOrFail($jane);
        $this->fakeGoogle();

        $this->googleLogin();

        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['success']);
        $this->assertCount(0, $this->mailTo('jane@example.com'));
    }
}

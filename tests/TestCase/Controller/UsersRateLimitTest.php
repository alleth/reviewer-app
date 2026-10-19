<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Service\RateLimiter;
use App\Test\TestSuite\ApiTestTrait;
use App\Test\TestSuite\XenditTestTrait;
use Cake\I18n\DateTime;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * H4: sign-in escalation and step-up, the code-guess budget across new codes,
 * forgot-password limits, per-IP limits, the signed-in wrong-password limit
 * and its notice, checkout's limit, the 429 format and the modes.
 *
 * Fixture user 1 (janedelacruz / jane@example.com) is verified with a password.
 *
 * @uses \App\Controller\UsersController
 * @uses \App\Controller\PaymentsController::checkout()
 */
class UsersRateLimitTest extends TestCase
{
    use ApiTestTrait;
    use IntegrationTestTrait;
    use XenditTestTrait;

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
    private const IP = '198.51.100.20';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnv('BREVO_API_KEY', '');
        $this->setEnv('EMAIL_TRANSPORT_DEFAULT_URL', '');
        $this->setEnv('RATE_LIMIT_MODE', null);
        $this->setEnv('RATE_LIMIT_IP_MODE', null);
        $this->setEnv('TURNSTILE_SECRET_KEY', null);
        $this->fakeXenditEnv();
        DateTime::setTestNow(new DateTime('2026-10-18 12:00:00'));
        Log::setConfig('test_mail', ['className' => 'Array', 'levels' => ['warning']]);
    }

    protected function tearDown(): void
    {
        Log::drop('test_mail');
        DateTime::setTestNow(null);
        $this->restoreEnv();
        parent::tearDown();
    }

    /**
     * The next request as the SPA sends it, from $ip (the rightmost
     * X-Forwarded-For entry, as Railway's proxy appends it).
     */
    private function fromIp(string $ip = self::IP): void
    {
        $this->replaceRequest(['headers' => [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
            'X-Forwarded-For' => $ip,
        ]]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function login(string $name, string $password, array $data = []): void
    {
        $this->fromIp();
        $this->post('/api/login', ['user_name' => $name, 'user_pass' => $password] + $data);
    }

    private function advance(int $seconds): void
    {
        DateTime::setTestNow((new DateTime())->modify("+{$seconds} seconds"));
    }

    private function events(): Table
    {
        return $this->getTableLocator()->get('RateLimits');
    }

    /**
     * Pre-records $count events for (bucket, key), $ago seconds back.
     */
    private function addEvents(string $bucket, string $key, int $count, int $ago = 60): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->events()->saveOrFail($this->events()->newEntity([
                'bucket' => $bucket,
                'key_hash' => $key,
                'created' => (new DateTime())->modify("-{$ago} seconds"),
            ]));
        }
    }

    private function jane(): string
    {
        return RateLimiter::key('u', '1');
    }

    /**
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

    private function assertRateLimited(): void
    {
        $this->assertResponseCode(429);
        $body = $this->responseJson();
        $this->assertFalse($body['success']);
        $this->assertSame('rate_limited', $body['reason']);
        $this->assertIsInt($body['retryAfter']);
        $this->assertGreaterThan(0, $body['retryAfter']);
        $this->assertHeader('Retry-After', (string)$body['retryAfter']);
        $this->assertStringContainsString('Please try again in', (string)$body['message']);
    }

    // ---- sign-in escalation ----------------------------------------------

    public function testFiveFailuresBringAThirtySecondWaitThatPasses(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('janedelacruz', 'wrong-password');
            $this->assertSame('Invalid username or password', $this->responseJson()['message']);
        }

        $this->login('janedelacruz', self::PASSWORD);
        $this->assertRateLimited();
        $this->assertSame(30, $this->responseJson()['retryAfter']);

        $this->advance(31);
        $this->login('janedelacruz', self::PASSWORD);
        $this->assertTrue($this->responseJson()['success']);
        $this->assertSame(0, $this->events()->find()->where(['bucket' => 'login_fail'])->count(), 'Cleared');
    }

    public function testUsernameAndEmailShareOneBucket(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->login('janedelacruz', 'wrong-password');
        }
        for ($i = 0; $i < 2; $i++) {
            $this->login('JANE@example.com', 'wrong-password');
        }

        $this->login('jane@example.com', 'wrong-password');

        $this->assertRateLimited();
    }

    public function testAnUnknownNameIsDelayedTheSameWay(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('nobody-here', 'whatever');
            $this->assertSame('Invalid username or password', $this->responseJson()['message']);
        }

        $this->login('nobody-here', 'whatever');

        $this->assertRateLimited();
        $this->assertSame(30, $this->responseJson()['retryAfter']);
    }

    public function testTenFailuresMakeACorrectPasswordNeedAnEmailedCode(): void
    {
        $this->addEvents('login_fail', $this->jane(), 10, 120);

        // A wrong password still reads as plain invalid: nothing revealed.
        $this->login('janedelacruz', 'wrong-password');
        $this->assertSame('Invalid username or password', $this->responseJson()['message']);
        $this->assertArrayNotHasKey('needsCode', $this->responseJson());

        $this->advance(31);
        $this->login('janedelacruz', self::PASSWORD);
        $body = $this->responseJson();
        $this->assertFalse($body['success']);
        $this->assertTrue($body['needsCode']);
        $this->assertSessionNotHasKey('Auth.User');
        $mail = $this->mailTo('jane@example.com');
        $this->assertCount(1, $mail);
        $this->assertStringContainsString('several unsuccessful attempts', $mail[0]);

        $this->login('janedelacruz', self::PASSWORD, ['code' => $this->lastCode('jane@example.com')]);
        $this->assertTrue($this->responseJson()['success']);
        $this->assertSame(0, $this->events()->find()->where(['bucket' => 'login_fail'])->count());
    }

    public function testTwentyFailuresMeanFifteenMinutesAndFiftyADayAnHour(): void
    {
        $this->addEvents('login_fail', $this->jane(), 20, 60);
        $this->login('janedelacruz', self::PASSWORD);
        $this->assertRateLimited();
        $this->assertSame(900 - 60, $this->responseJson()['retryAfter']);

        // 50 in a day, the last 30 minutes ago: the 15-minute wait would be
        // over, but the day tier's hour isn't.
        $this->events()->deleteAll([]);
        $this->addEvents('login_fail', $this->jane(), 50, 1800);
        $this->login('janedelacruz', self::PASSWORD);
        $this->assertRateLimited();
        $this->assertSame(3600 - 1800, $this->responseJson()['retryAfter']);
    }

    public function testAPasswordResetClearsSignInFailures(): void
    {
        $this->addEvents('login_fail', $this->jane(), 20, 60);

        $this->fromIp();
        $this->post('/api/password/forgot', ['email' => 'jane@example.com']);
        $this->fromIp();
        $this->post('/api/password/reset', [
            'email' => 'jane@example.com',
            'code' => $this->lastCode('jane@example.com'),
            'password' => 'NewPassword456',
        ]);
        $this->assertTrue($this->responseJson()['success']);

        $this->login('janedelacruz', 'NewPassword456');
        $this->assertTrue($this->responseJson()['success'], 'Recovery never leaves the owner waiting');
    }

    // ---- forgot password and the code-guess budget -----------------------

    public function testForgotPasswordSendsAtMostOneCodeAMinuteAndTwentyADay(): void
    {
        $this->fromIp();
        $this->post('/api/password/forgot', ['email' => 'jane@example.com']);
        $this->fromIp();
        $this->post('/api/password/forgot', ['email' => 'jane@example.com']);
        $this->assertResponseOk();
        $this->assertSame('If that email has an account, a reset code is on its way.', $this->responseJson()['message']);
        $this->assertCount(1, $this->mailTo('jane@example.com'));

        $codes = $this->getTableLocator()->get('AuthCodes');
        $codes->deleteAll([]);
        for ($i = 0; $i < 20; $i++) {
            $codes->saveOrFail($codes->newEntity([
                'user_id' => 1,
                'purpose' => 'password_reset_sent',
                'code_hash' => '',
                'expires' => (new DateTime())->modify('+1 day'),
                'attempts' => 0,
                'created' => (new DateTime())->modify('-' . (2 + $i) . ' minutes'),
            ]));
        }
        $this->fromIp();
        $this->post('/api/password/forgot', ['email' => 'jane@example.com']);
        $this->assertResponseOk();
        $this->assertCount(1, $this->mailTo('jane@example.com'), 'The daily cap holds; the answer is the same');
    }

    public function testNewCodesNoLongerBringFreshGuesses(): void
    {
        $this->fromIp();
        $this->post('/api/password/forgot', ['email' => 'jane@example.com']);
        for ($round = 0; $round < 2; $round++) {
            for ($i = 0; $i < 5; $i++) {
                $this->fromIp();
                $this->post('/api/password/reset', [
                    'email' => 'jane@example.com',
                    'code' => '000000',
                    'password' => 'NewPassword456',
                ]);
                $this->assertResponseCode(400);
            }
            $this->advance(61);
            $this->fromIp();
            $this->post('/api/password/forgot', ['email' => 'jane@example.com']);
        }

        // Ten wrong guesses this hour: even the right new code isn't checked.
        $this->fromIp();
        $this->post('/api/password/reset', [
            'email' => 'jane@example.com',
            'code' => $this->lastCode('jane@example.com'),
            'password' => 'NewPassword456',
        ]);
        $this->assertRateLimited();
    }

    public function testTheDailyGuessBudgetIsFifty(): void
    {
        $this->addEvents('code_guess:password_reset', $this->jane(), 49, 7200);
        $this->fromIp();
        $this->post('/api/password/reset', ['email' => 'jane@example.com', 'code' => '000000', 'password' => 'NewPassword456']);
        $this->assertResponseCode(400, '49 earlier today, under the hourly limit: still checked');

        $this->fromIp();
        $this->post('/api/password/reset', ['email' => 'jane@example.com', 'code' => '000000', 'password' => 'NewPassword456']);
        $this->assertRateLimited();
    }

    public function testAnUnknownEmailGetsTheSameBudget(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->fromIp();
            $this->post('/api/password/reset', ['email' => 'nobody@example.org', 'code' => '000000', 'password' => 'NewPassword456']);
            $this->assertResponseCode(400);
        }

        $this->fromIp();
        $this->post('/api/password/reset', ['email' => 'NOBODY@example.org', 'code' => '000000', 'password' => 'NewPassword456']);
        $this->assertRateLimited();
    }

    public function testEmailChangeCodesHaveABudgetToo(): void
    {
        $this->addEvents('code_guess:change_email', $this->jane(), 10, 60);
        $this->loginAs(1);
        $this->asAjax();
        $this->post('/api/account/email', ['new_email' => 'new.jane@example.org', 'code' => '123456']);

        $this->assertRateLimited();
    }

    // ---- per-IP limits ---------------------------------------------------

    public function testPerIpLimitsOnlyLogUntilEnforced(): void
    {
        $this->addEvents('google_ip', RateLimiter::key('ip', self::IP), 30, 60);

        $this->fromIp();
        $this->post('/api/google-login', []);
        $this->assertResponseCode(400, 'Log mode: let through to the normal "missing credential" answer');

        $this->setEnv('RATE_LIMIT_IP_MODE', 'enforce');
        $this->fromIp();
        $this->post('/api/google-login', []);
        $this->assertRateLimited();

        $this->fromIp('198.51.100.99');
        $this->post('/api/google-login', []);
        $this->assertResponseCode(400, 'Another IP is unaffected');
    }

    public function testAFakedForwardedForDoesNotChangeTheKey(): void
    {
        $this->setEnv('RATE_LIMIT_IP_MODE', 'enforce');
        $this->addEvents('forgot_ip', RateLimiter::key('ip', self::IP), 10, 60);

        $this->fromIp('1.2.3.4, ' . self::IP);
        $this->post('/api/password/forgot', ['email' => 'jane@example.com']);

        $this->assertRateLimited();
    }

    /**
     * TEMPORARY staging diagnostic: one line per per-IP check on staging only,
     * with the header shape and source but no address.
     */
    public function testClientIpDiagnosticIsStagingOnlyAndCarriesNoAddress(): void
    {
        Log::setConfig('test_diag', ['className' => 'Array', 'levels' => ['info'], 'scopes' => ['ratelimit']]);
        $engine = Log::engine('test_diag');
        $this->assertInstanceOf(ArrayLog::class, $engine);
        $diag = fn(): array => array_values(array_filter(
            $engine->read(),
            fn(string $line): bool => str_contains($line, '[clientip diag]'),
        ));

        try {
            $this->setEnv('RAILWAY_ENVIRONMENT_NAME', 'production');
            $this->fromIp('203.0.113.9, ' . self::IP);
            $this->post('/api/password/forgot', ['email' => 'jane@example.com']);
            $this->assertSame([], $diag(), 'Nothing outside staging');

            $this->setEnv('RAILWAY_ENVIRONMENT_NAME', 'staging');
            $this->fromIp('203.0.113.9, ' . self::IP);
            $this->post('/api/password/forgot', ['email' => 'jane@example.com']);
            $lines = $diag();
            $this->assertCount(1, $lines);
            $this->assertStringContainsString(
                'bucket=forgot_ip xff=yes xff_entries=2 x_real_ip=no x_envoy_external_address=no'
                . ' remote_addr_fallback=no source=xff_rightmost',
                $lines[0],
            );
            $this->assertStringNotContainsString('203.0', $lines[0]);
            $this->assertStringNotContainsString('198.51', $lines[0]);
            $this->assertStringNotContainsString('jane', $lines[0]);
        } finally {
            Log::drop('test_diag');
        }
    }

    public function testRegistrationCountsOnlyCreatedAccountsPerIp(): void
    {
        $this->setEnv('RATE_LIMIT_IP_MODE', 'enforce');
        for ($i = 0; $i < 5; $i++) {
            $this->fromIp();
            $this->post('/api/register', [
                'fname' => 'New',
                'lname' => 'Person',
                'email' => "new{$i}@example.org",
                'user_name' => "newperson{$i}",
                'user_pass' => 'Password123',
            ]);
            $this->assertTrue($this->responseJson()['needsVerification'] ?? false);
        }
        // A failed attempt (taken username) doesn't count...
        $this->fromIp();
        $this->post('/api/register', ['email' => 'x@example.org', 'user_name' => 'newperson0', 'user_pass' => 'Password123']);
        $this->assertSame(5, $this->events()->find()->where(['bucket' => 'register_ip'])->count());

        // ...but a sixth new account this hour is refused.
        $this->fromIp();
        $this->post('/api/register', ['email' => 'six@example.org', 'user_name' => 'newperson6', 'user_pass' => 'Password123']);
        $this->assertRateLimited();
    }

    // ---- signed in: wrong current passwords -------------------------------

    public function testFiveWrongCurrentPasswordsWaitAndTellTheOwnerOnce(): void
    {
        $this->loginAs(1);
        for ($i = 0; $i < 4; $i++) {
            $this->asAjax();
            $this->post('/api/password/change', ['current_password' => 'wrong-one', 'new_password' => 'Another12345']);
            $this->assertResponseCode(400);
        }
        // The email-change flow counts in the same bucket.
        $this->asAjax();
        $this->post('/api/account/email', ['new_email' => 'new.jane@example.org', 'current_password' => 'wrong-one']);
        $this->assertResponseCode(400);

        $notice = array_values(array_filter(
            $this->mailTo('jane@example.com'),
            fn(string $line): bool => str_contains($line, 'Several wrong password attempts'),
        ));
        $this->assertCount(1, $notice);

        $this->asAjax();
        $this->post('/api/password/change', ['current_password' => self::PASSWORD, 'new_password' => 'Another12345']);
        $this->assertRateLimited();

        $this->asAjax();
        $this->post('/api/password/change', ['current_password' => 'wrong-one', 'new_password' => 'Another12345']);
        $this->assertCount(1, array_filter(
            $this->mailTo('jane@example.com'),
            fn(string $line): bool => str_contains($line, 'Several wrong password attempts'),
        ), 'Only one notice per window');

        $this->advance(901);
        $this->asAjax();
        $this->post('/api/password/change', ['current_password' => self::PASSWORD, 'new_password' => 'Another12345']);
        $this->assertTrue($this->responseJson()['success']);
        $this->assertSame(0, $this->events()->find()->where(['bucket' => 'reauth_fail'])->count(), 'Cleared');
    }

    // ---- checkout --------------------------------------------------------

    public function testCheckoutLimitsNewInvoicesPerHour(): void
    {
        $this->addEvents('checkout_new', $this->jane(), 10, 60);
        $this->loginAs(1);
        $this->asAjax();
        $this->post('/api/checkout', ['reviewer' => 'civil-service', 'plan_id' => '7-day']);

        // No Xendit call was faked, so reaching Xendit would have failed differently.
        $this->assertRateLimited();
        $this->assertSame(0, $this->getTableLocator()->get('Passes')->find()->where(['status' => 'pending'])->count());
    }

    // ---- modes -----------------------------------------------------------

    public function testOffModeSwitchesEverythingOff(): void
    {
        $this->setEnv('RATE_LIMIT_MODE', 'off');
        for ($i = 0; $i < 6; $i++) {
            $this->login('janedelacruz', 'wrong-password');
            $this->assertResponseOk();
        }

        $this->login('janedelacruz', self::PASSWORD);
        $this->assertTrue($this->responseJson()['success']);
        $this->assertSame(0, $this->events()->find()->count());
    }

    public function testLogModeLetsEverythingThrough(): void
    {
        $this->setEnv('RATE_LIMIT_MODE', 'log');
        $this->addEvents('login_fail', $this->jane(), 25, 10);

        $this->login('janedelacruz', self::PASSWORD);

        $this->assertTrue($this->responseJson()['success'], 'No wait and no step-up in log mode');
    }
}

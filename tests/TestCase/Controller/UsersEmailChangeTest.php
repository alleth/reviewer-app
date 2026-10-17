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
 * H3: changing the account email needs re-authentication (the current
 * password, or for a Google-only account a code sent to the current address)
 * and a code from the new address, which only works for that address. The
 * switch marks the address verified, signs out other devices, drops
 * outstanding codes and tells both addresses. Email comparisons ignore letter
 * case everywhere, including sign-in, Google sign-in, password reset and
 * registration.
 *
 * Fixture user 1 (jane@example.com) is verified and has a password. Tests add
 * a Google-only user where needed.
 *
 * @uses \App\Controller\UsersController::changeEmail()
 * @uses \App\Controller\UsersController::updateAccount()
 */
class UsersEmailChangeTest extends TestCase
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
        'app.Passes',
    ];

    private const PASSWORD = 'Password123';
    private const NEW_EMAIL = 'new.jane@example.org';

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
     * @param array<string, mixed> $data
     */
    private function changeEmail(array $data): void
    {
        $this->asAjax();
        $this->post('/api/account/email', $data);
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

    private function codes(): Table
    {
        return $this->getTableLocator()->get('AuthCodes');
    }

    private function user(int $id): User
    {
        /** @var \App\Model\Entity\User $user */
        $user = $this->users()->get($id);

        return $user;
    }

    /**
     * A second account (id 2): verified, Google-only (no password) unless a
     * password hash is given.
     */
    private function addUser(string $email, ?string $passwordHash = null): User
    {
        /** @var \App\Model\Entity\User $user */
        $user = $this->users()->newEntity([
            'fname' => 'Gina',
            'lname' => 'Santos',
            'email' => $email,
            'user_name' => 'gina' . substr(md5($email), 0, 6),
        ]);
        $user->user_pass = $passwordHash;
        $user->google_id = $passwordHash === null ? 'google-sub-gina' : null;
        $user->email_verified_at = new DateTime('2026-10-01 00:00:00');
        $this->users()->saveOrFail($user);

        return $user;
    }

    /**
     * The `Practice`-style CLI gotcha: a completed request closes the session,
     * so read what it wrote straight from $_SESSION.
     *
     * @return array<string, mixed>
     */
    private function sessionAuth(): array
    {
        return (array)($_SESSION['Auth'] ?? []);
    }

    /**
     * Jane asks for the change with her password and gets the new-address code.
     */
    private function requestAsJane(string $newEmail = self::NEW_EMAIL): string
    {
        $this->changeEmail(['new_email' => $newEmail, 'current_password' => self::PASSWORD]);
        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['needsCode'] ?? false);

        return $this->lastCode($newEmail);
    }

    public function testAWrongPasswordSendsNothingAndChangesNothing(): void
    {
        $this->loginAs(1);
        $this->changeEmail(['new_email' => self::NEW_EMAIL, 'current_password' => 'wrong-password']);

        $this->assertResponseCode(400);
        $this->assertSame('Your current password is incorrect.', $this->responseJson()['message']);
        $this->assertSame([], $this->mailTo(self::NEW_EMAIL));
        $this->assertSame('jane@example.com', $this->user(1)->email);
    }

    public function testAMissingPasswordIs422(): void
    {
        $this->loginAs(1);
        $this->changeEmail(['new_email' => self::NEW_EMAIL]);

        $this->assertResponseCode(422);
        $this->assertSame('Enter your current password.', $this->responseJson()['message']);
        $this->assertSame([], $this->mailTo(self::NEW_EMAIL));
    }

    public function testTheRightPasswordSendsACodeOnlyToTheNewAddress(): void
    {
        $this->loginAs(1);
        $this->requestAsJane();

        $json = $this->responseJson();
        $this->assertFalse($json['success']);
        $this->assertSame(self::NEW_EMAIL, $json['email']);
        $this->assertCount(1, $this->mailTo(self::NEW_EMAIL));
        $this->assertSame([], $this->mailTo('jane@example.com'));
        $this->assertSame('jane@example.com', $this->user(1)->email, 'Nothing changes before the code');
    }

    public function testTheRightCodeSwitchesTheAddressMarksItVerifiedAndTellsBoth(): void
    {
        $this->loginAs(1);
        $code = $this->requestAsJane();

        $this->changeEmail(['new_email' => self::NEW_EMAIL, 'code' => $code]);

        $this->assertResponseOk();
        $json = $this->responseJson();
        $this->assertTrue($json['success']);
        $this->assertSame(self::NEW_EMAIL, $json['user']['email']);
        $this->assertArrayHasKey('purchases', $json['user']);

        $jane = $this->user(1);
        $this->assertSame(self::NEW_EMAIL, $jane->email);
        $this->assertNotNull($jane->email_verified_at);
        $this->assertTrue($jane->email_verified_at->greaterThan(new DateTime('2026-10-02 00:00:00')));

        $notice = $this->mailTo('jane@example.com');
        $this->assertCount(1, $notice);
        $this->assertStringContainsString('was changed', $notice[0]);
        $this->assertStringContainsString('n***@example.org', $notice[0]);
        $this->assertStringNotContainsString(self::NEW_EMAIL, $notice[0], 'The notice masks the new address');
        $this->assertStringContainsString('support@career-pass.org', $notice[0]);

        $newMail = $this->mailTo(self::NEW_EMAIL);
        $this->assertCount(2, $newMail, 'The code, then the confirmation');
        $this->assertStringContainsString('This is now the email address', $newMail[1]);
    }

    public function testOtherDevicesAreSignedOutAndThisOneStaysIn(): void
    {
        $this->loginAs(1);
        $code = $this->requestAsJane();

        $this->changeEmail(['new_email' => self::NEW_EMAIL, 'code' => $code]);
        $this->assertResponseOk();

        $auth = $this->sessionAuth();
        $this->assertSame($this->user(1)->session_token, $auth['token'] ?? null, 'This device holds the new token');
        $this->assertSame(self::NEW_EMAIL, $auth['User']->email ?? null);

        // The session set up by loginAs() still carries the old token: that's
        // what any other device signed in before the change looks like.
        $this->get('/api/session');
        $this->assertFalse($this->responseJson()['loggedIn']);
        $this->assertSame('signed_in_elsewhere', $this->responseJson()['reason']);
    }

    public function testACodeOnlyWorksForTheAddressItWasSentToIgnoringCase(): void
    {
        $this->loginAs(1);
        $code = $this->requestAsJane();

        $this->changeEmail(['new_email' => 'someone.else@example.org', 'code' => $code]);
        $this->assertResponseCode(400);
        $this->assertSame('jane@example.com', $this->user(1)->email);

        $this->changeEmail(['new_email' => '  New.Jane@EXAMPLE.org ', 'code' => $code]);
        $this->assertResponseOk();
        $this->assertSame('New.Jane@EXAMPLE.org', $this->user(1)->email, 'Stored as typed, trimmed');
    }

    public function testWrongCodesAreRefusedAndTheFifthKillsIt(): void
    {
        $this->loginAs(1);
        $code = $this->requestAsJane();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->changeEmail(['new_email' => self::NEW_EMAIL, 'code' => $wrong]);
            $this->assertResponseCode(400);
        }
        $this->changeEmail(['new_email' => self::NEW_EMAIL, 'code' => $code]);

        $this->assertResponseCode(400);
        $this->assertSame('jane@example.com', $this->user(1)->email);
    }

    public function testAnExpiredCodeIsRefused(): void
    {
        $this->loginAs(1);
        $code = $this->requestAsJane();
        $this->codes()->updateAll(
            ['expires' => (new DateTime())->modify('-1 minute')],
            ['user_id' => 1, 'purpose' => 'change_email'],
        );

        $this->changeEmail(['new_email' => self::NEW_EMAIL, 'code' => $code]);

        $this->assertResponseCode(400);
        $this->assertSame('jane@example.com', $this->user(1)->email);
    }

    public function testOutstandingCodesAreDeletedButSendLimitsAreKept(): void
    {
        $this->loginAs(1);
        // A password-reset code already on its way to the old inbox.
        $this->asAjax();
        $this->post('/api/password/forgot', ['email' => 'jane@example.com']);
        $this->assertSame(1, $this->codes()->find()->where(['user_id' => 1, 'purpose' => 'password_reset'])->count());

        $code = $this->requestAsJane();
        $this->changeEmail(['new_email' => self::NEW_EMAIL, 'code' => $code]);
        $this->assertResponseOk();

        $left = $this->codes()->find()->where(['user_id' => 1])->all()->extract('purpose')->toList();
        $this->assertSame(['change_email_sent'], $left);
    }

    public function testTheCurrentAddressInAnotherCaseIs422(): void
    {
        $this->loginAs(1);
        $this->changeEmail(['new_email' => 'JANE@Example.COM', 'current_password' => self::PASSWORD]);

        $this->assertResponseCode(422);
        $this->assertSame("That's already your email address.", $this->responseJson()['message']);
    }

    public function testAnAddressTakenInAnotherCaseIs422(): void
    {
        $this->addUser('Taken@Example.com');
        $this->loginAs(1);
        $this->changeEmail(['new_email' => 'taken@example.COM', 'current_password' => self::PASSWORD]);

        $this->assertResponseCode(422);
        $this->assertSame('This email is already registered.', $this->responseJson()['message']);
        $this->assertSame([], $this->mailTo('taken@example.COM'));
    }

    public function testMalformedAndOverlongAddressesAre422(): void
    {
        $this->loginAs(1);
        $cases = [
            '' => 'Enter a valid email address.',
            'not-an-email' => 'Enter a valid email address.',
            str_repeat('a', 95) . '@example.org' => 'Email must be 100 characters or fewer.',
        ];
        foreach ($cases as $email => $message) {
            $this->changeEmail(['new_email' => $email, 'current_password' => self::PASSWORD]);
            $this->assertResponseCode(422, (string)$email);
            $this->assertSame($message, $this->responseJson()['message']);
        }

        $this->changeEmail(['new_email' => ['x@example.org'], 'current_password' => self::PASSWORD]);
        $this->assertResponseCode(422);
    }

    public function testAnAddressTakenBetweenTheStepsIs422(): void
    {
        $this->loginAs(1);
        $code = $this->requestAsJane();
        $this->addUser('NEW.JANE@example.org');

        $this->changeEmail(['new_email' => self::NEW_EMAIL, 'code' => $code]);

        $this->assertResponseCode(422);
        $this->assertSame('This email is already registered.', $this->responseJson()['message']);
        $this->assertSame('jane@example.com', $this->user(1)->email);
    }

    public function testResendingRespectsTheCooldownAndTheDailyCap(): void
    {
        $this->loginAs(1);
        $first = $this->requestAsJane();
        $this->requestAsJane();
        $this->assertCount(1, $this->mailTo(self::NEW_EMAIL), 'No second code inside the cooldown');

        $this->codes()->updateAll(
            ['created' => (new DateTime())->modify('-61 seconds')],
            ['user_id' => 1, 'purpose' => 'change_email_sent'],
        );
        $second = $this->requestAsJane();
        $this->assertCount(2, $this->mailTo(self::NEW_EMAIL));

        // The new code replaces the old one.
        if ($first !== $second) {
            $this->changeEmail(['new_email' => self::NEW_EMAIL, 'code' => $first]);
            $this->assertResponseCode(400);
        }

        $this->codes()->deleteAll(['user_id' => 1]);
        for ($i = 0; $i < 10; $i++) {
            $this->codes()->saveOrFail($this->codes()->newEntity([
                'user_id' => 1,
                'purpose' => 'change_email_sent',
                'code_hash' => '',
                'expires' => (new DateTime())->modify('+1 day'),
                'attempts' => 0,
                'created' => (new DateTime())->modify('-' . (2 + $i) . ' hours'),
            ]));
        }
        $this->changeEmail(['new_email' => self::NEW_EMAIL, 'current_password' => self::PASSWORD]);
        $this->assertResponseOk();
        $this->assertCount(2, $this->mailTo(self::NEW_EMAIL), 'The daily cap stops further codes');
    }

    public function testEmailChangeCodesHaveTheirOwnLimit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->codes()->saveOrFail($this->codes()->newEntity([
                'user_id' => 1,
                'purpose' => 'verify_email_sent',
                'code_hash' => '',
                'expires' => (new DateTime())->modify('+1 day'),
                'attempts' => 0,
                'created' => new DateTime(),
            ]));
        }
        $this->loginAs(1);
        $this->requestAsJane();

        $this->assertCount(1, $this->mailTo(self::NEW_EMAIL));
    }

    public function testAGoogleOnlyAccountIsFirstSentACodeAtItsCurrentAddress(): void
    {
        $gina = $this->addUser('gina@example.com');
        $this->loginAs((int)$gina->user_id);

        $this->changeEmail(['new_email' => 'gina.new@example.org']);

        $this->assertResponseOk();
        $json = $this->responseJson();
        $this->assertTrue($json['needsCurrentCode']);
        $this->assertSame('g***@example.com', $json['email'], 'The current address is masked');
        $this->assertStringNotContainsString('gina@example.com', (string)$json['message']);
        $toCurrent = $this->mailTo('gina@example.com');
        $this->assertCount(1, $toCurrent);
        $this->assertStringContainsString('g***@example.org', $toCurrent[0]);
        $this->assertSame([], $this->mailTo('gina.new@example.org'));
    }

    public function testAGoogleOnlyAccountCannotSkipTheCurrentAddressCodeWithAPassword(): void
    {
        $gina = $this->addUser('gina@example.com');
        $this->loginAs((int)$gina->user_id);

        $this->changeEmail(['new_email' => 'gina.new@example.org', 'current_password' => 'anything-at-all']);

        $this->assertTrue($this->responseJson()['needsCurrentCode'] ?? false);
        $this->assertSame([], $this->mailTo('gina.new@example.org'));
    }

    public function testAWrongCurrentAddressCodeIsRefused(): void
    {
        $gina = $this->addUser('gina@example.com');
        $this->loginAs((int)$gina->user_id);
        $this->changeEmail(['new_email' => 'gina.new@example.org']);
        $code = $this->lastCode('gina@example.com');

        $this->changeEmail([
            'new_email' => 'gina.new@example.org',
            'current_code' => $code === '000000' ? '111111' : '000000',
        ]);
        $this->assertResponseCode(400);
        $this->assertSame([], $this->mailTo('gina.new@example.org'));

        // Tied to the new address it was sent for.
        $this->changeEmail(['new_email' => 'other@example.org', 'current_code' => $code]);
        $this->assertResponseCode(400);
        $this->assertSame([], $this->mailTo('other@example.org'));
    }

    public function testAGoogleOnlyAccountCompletesTheThreeSteps(): void
    {
        $gina = $this->addUser('gina@example.com');
        $id = (int)$gina->user_id;
        $this->loginAs($id);

        $this->changeEmail(['new_email' => 'gina.new@example.org']);
        $currentCode = $this->lastCode('gina@example.com');

        $this->changeEmail(['new_email' => 'gina.new@example.org', 'current_code' => $currentCode]);
        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['needsCode']);
        $this->assertCount(1, $this->mailTo('gina.new@example.org'));

        // "Send a new code" repeats that request: the current-address code
        // still works for it.
        $this->codes()->updateAll(
            ['created' => (new DateTime())->modify('-61 seconds')],
            ['user_id' => $id, 'purpose' => 'change_email_sent'],
        );
        $this->changeEmail(['new_email' => 'gina.new@example.org', 'current_code' => $currentCode]);
        $this->assertTrue($this->responseJson()['needsCode']);
        $this->assertCount(2, $this->mailTo('gina.new@example.org'));

        $this->changeEmail(['new_email' => 'gina.new@example.org', 'code' => $this->lastCode('gina.new@example.org')]);
        $this->assertResponseOk();
        $this->assertSame('gina.new@example.org', $this->user($id)->email);
        $this->assertSame(
            0,
            $this->codes()->find()->where(['user_id' => $id, 'purpose' => 'email_change_reauth'])->count(),
            'The current-address code is gone once the change is done',
        );
    }

    public function testACurrentAddressCodeCannotConfirmTheChange(): void
    {
        $gina = $this->addUser('gina@example.com');
        $this->loginAs((int)$gina->user_id);
        $this->changeEmail(['new_email' => 'gina.new@example.org']);

        $this->changeEmail(['new_email' => 'gina.new@example.org', 'code' => $this->lastCode('gina@example.com')]);

        $this->assertResponseCode(400);
        $this->assertSame('gina@example.com', $this->user((int)$gina->user_id)->email);
    }

    public function testChangingEmailNeedsASessionTheAjaxHeaderAndAPost(): void
    {
        $this->changeEmail(['new_email' => self::NEW_EMAIL, 'current_password' => self::PASSWORD]);
        $this->assertResponseCode(401);
        $this->assertSame('signed_out', $this->responseJson()['reason']);

        $this->loginAs(1);
        $this->post('/api/account/email', ['new_email' => self::NEW_EMAIL, 'current_password' => self::PASSWORD]);
        $this->assertResponseCode(403);

        $this->asAjax();
        $this->get('/api/account/email');
        $this->assertResponseCode(405);
        $this->assertSame([], $this->mailTo(self::NEW_EMAIL));
    }

    public function testTheProfileFormCanNoLongerChangeTheEmail(): void
    {
        $this->loginAs(1);
        $this->asAjax();
        $this->post('/api/account', ['fname' => 'Janet', 'email' => 'someone@example.org']);

        $this->assertResponseCode(422);
        $this->assertSame(
            "Use 'Change email' in Settings to update your email address.",
            $this->responseJson()['message'],
        );
        $jane = $this->user(1);
        $this->assertSame('jane@example.com', $jane->email);
        $this->assertNotSame('Janet', $jane->fname, 'Nothing is saved');
    }

    public function testTheProfileFormStillSavesNamesWithTheSameEmailInAnyCase(): void
    {
        $this->loginAs(1);
        $this->asAjax();
        $this->post('/api/account', ['fname' => 'Janet', 'email' => 'JANE@example.com']);

        $this->assertResponseOk();
        $jane = $this->user(1);
        $this->assertSame('Janet', $jane->fname);
        $this->assertSame('jane@example.com', $jane->email);
    }

    public function testSignInUsesTheNewAddressInAnyCaseAndNotTheOldOne(): void
    {
        $this->loginAs(1);
        $code = $this->requestAsJane();
        $this->changeEmail(['new_email' => self::NEW_EMAIL, 'code' => $code]);
        $this->assertResponseOk();

        $this->asAjax();
        $this->post('/api/login', ['user_name' => 'jane@example.com', 'user_pass' => self::PASSWORD]);
        $this->assertFalse($this->responseJson()['success']);

        $this->asAjax();
        $this->post('/api/login', ['user_name' => 'NEW.Jane@Example.ORG', 'user_pass' => self::PASSWORD]);
        $this->assertTrue($this->responseJson()['success']);
    }

    public function testRegisterAndPasswordResetMatchEmailsIgnoringCase(): void
    {
        $this->asAjax();
        $this->post('/api/register', [
            'fname' => 'Copy',
            'lname' => 'Cat',
            'email' => 'Jane@EXAMPLE.com',
            'user_name' => 'copycat',
            'user_pass' => 'Password123',
        ]);
        $this->assertFalse($this->responseJson()['success']);
        $this->assertSame(
            'This email is already registered.',
            $this->responseJson()['errors']['email']['uniqueEmail'] ?? null,
        );

        $this->asAjax();
        $this->post('/api/password/forgot', ['email' => ' JANE@Example.com ']);
        $this->assertCount(1, $this->mailTo('jane@example.com'), 'The reset code goes to the stored address');
    }

    /**
     * End to end with mixed-case addresses on both sides: the current address
     * is matched in any case, the new one is confirmed with a differently cased
     * copy, it's stored exactly as typed at confirmation, and every later
     * lookup (sign-in, password reset, uniqueness) ignores case.
     */
    public function testAMixedCaseChangeEndToEnd(): void
    {
        $mike = $this->addUser('Mike.Reyes@Example.COM', password_hash(self::PASSWORD, PASSWORD_DEFAULT));
        $id = (int)$mike->user_id;
        $this->loginAs($id);

        // The current address in another case is still "the current address".
        $this->changeEmail(['new_email' => 'mike.reyes@example.com', 'current_password' => self::PASSWORD]);
        $this->assertResponseCode(422);
        $this->assertSame("That's already your email address.", $this->responseJson()['message']);

        // Ask with one casing...
        $this->changeEmail(['new_email' => 'Mike.NEW@Example.Org', 'current_password' => self::PASSWORD]);
        $this->assertTrue($this->responseJson()['needsCode'] ?? false);
        $code = $this->lastCode('Mike.NEW@Example.Org');

        // ...confirm with another: the code is bound to the address, not its case.
        $this->changeEmail(['new_email' => 'Mike.New@example.org', 'code' => $code]);
        $this->assertResponseOk();
        $this->assertSame('Mike.New@example.org', $this->responseJson()['user']['email']);

        $stored = $this->user($id);
        $this->assertSame('Mike.New@example.org', $stored->email, 'Stored exactly as typed at confirmation');
        $this->assertNotNull($stored->email_verified_at);

        // The notice went to the old address as it was stored.
        $this->assertCount(1, $this->mailTo('Mike.Reyes@Example.COM'));

        // Lookups ignore case: sign-in, password reset, uniqueness.
        $this->asAjax();
        $this->post('/api/login', ['user_name' => 'MIKE.NEW@EXAMPLE.ORG', 'user_pass' => self::PASSWORD]);
        $this->assertTrue($this->responseJson()['success']);

        $this->asAjax();
        $this->post('/api/login', ['user_name' => 'mike.reyes@example.com', 'user_pass' => self::PASSWORD]);
        $this->assertFalse($this->responseJson()['success'], 'The old address no longer signs in');

        $before = count($this->mailTo('Mike.New@example.org'));
        $this->asAjax();
        $this->post('/api/password/forgot', ['email' => 'mike.new@EXAMPLE.org']);
        $after = $this->mailTo('Mike.New@example.org');
        $this->assertCount($before + 1, $after, 'Reset code goes to the stored address');
        $this->assertStringContainsString('reset your CareerPass password', (string)end($after));

        $this->assertTrue($this->users()->emailTaken('MIKE.new@example.ORG'));
        $this->assertFalse($this->users()->emailTaken('mike.reyes@EXAMPLE.com'), 'The old address is free again');
    }

    public function testGoogleSignInMatchesTheAccountEmailIgnoringCase(): void
    {
        $this->mockService(GoogleTokenVerifierInterface::class, function () {
            return new class implements GoogleTokenVerifierInterface {
                public function verify(string $credential, string $clientId): ?array
                {
                    return [
                        'sub' => 'google-sub-jane',
                        'email' => 'JANE@Example.com',
                        'email_verified' => true,
                    ];
                }
            };
        });

        $this->asAjax();
        $this->post('/api/google-login', ['credential' => 'token']);

        // Jane's verified, unlinked account is found, so B3-L asks for a code
        // instead of creating a second account.
        $this->assertTrue($this->responseJson()['needsLinkCode'] ?? false);
        $this->assertSame(1, $this->users()->find()->count());
    }
}

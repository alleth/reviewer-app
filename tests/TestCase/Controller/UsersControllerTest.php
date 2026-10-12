<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestSuite\ApiTestTrait;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\UsersController Test Case
 *
 * Pins down the current login / session / logout behavior — the CSRF header
 * gate, the brute-force throttle and the single-device session — so later
 * auth changes (H1, B3, H4) can be made against a safety net.
 *
 * @uses \App\Controller\UsersController
 */
class UsersControllerTest extends TestCase
{
    use ApiTestTrait;
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.AuthCodes',
        'app.LoginEvents',
        'app.LoginAttempts',
        'app.Passes',
    ];

    /** Plaintext of the bcrypt digest stored for user 1 in UsersFixture. */
    private const PASSWORD = 'Password123';

    protected function tearDown(): void
    {
        $this->restoreEnv();
        parent::tearDown();
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::login()
     */
    public function testLoginWithoutAjaxHeaderIsForbidden(): void
    {
        $this->post('/api/login', ['user_name' => 'janedelacruz', 'user_pass' => self::PASSWORD]);

        $this->assertResponseCode(403);
        $this->assertSessionNotHasKey('Auth.User');
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::login()
     */
    public function testLoginWithCorrectPasswordStartsSingleDeviceSession(): void
    {
        $this->asAjax();
        $this->post('/api/login', ['user_name' => 'janedelacruz', 'user_pass' => self::PASSWORD]);

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertTrue($body['success']);
        $this->assertSame(1, $body['user']['user_id']);
        $this->assertSame([], $body['user']['purchases']);
        // Secrets never leave the server.
        $this->assertArrayNotHasKey('user_pass', $body['user']);
        $this->assertArrayNotHasKey('session_token', $body['user']);

        // The session token matches the account's, which is what single-device checks.
        $dbToken = $this->getTableLocator()->get('Users')->get(1)->session_token;
        $this->assertNotEmpty($dbToken);
        $this->assertSession($dbToken, 'Auth.token');
        $this->assertSame(1, $this->getTableLocator()->get('LoginEvents')->find()->count());
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::login()
     */
    public function testLoginAcceptsEmailAsIdentifier(): void
    {
        $this->asAjax();
        $this->post('/api/login', ['user_name' => 'jane@example.com', 'user_pass' => self::PASSWORD]);

        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['success']);
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::login()
     */
    public function testLoginWithWrongPasswordFailsAndIsCounted(): void
    {
        $this->asAjax();
        $this->post('/api/login', ['user_name' => 'JaneDelaCruz', 'user_pass' => 'wrong-password']);

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertFalse($body['success']);
        $this->assertSame('Invalid username or password', $body['message']);
        $this->assertSessionNotHasKey('Auth.User');

        // Counted against the lowercased identifier.
        $attempts = $this->getTableLocator()->get('LoginAttempts')->find()
            ->where(['identifier' => 'janedelacruz'])
            ->count();
        $this->assertSame(1, $attempts);
    }

    /**
     * After 10 failures in 15 minutes even the correct password is refused.
     *
     * @return void
     * @uses \App\Controller\UsersController::login()
     */
    public function testLoginIsThrottledAfterTenRecentFailures(): void
    {
        $attempts = $this->getTableLocator()->get('LoginAttempts');
        for ($i = 0; $i < 10; $i++) {
            $attempts->saveOrFail($attempts->newEntity([
                'identifier' => 'janedelacruz',
                'created' => new DateTime('-1 minute'),
            ]));
        }

        $this->asAjax();
        $this->post('/api/login', ['user_name' => 'janedelacruz', 'user_pass' => self::PASSWORD]);

        $this->assertResponseCode(429);
        $this->assertFalse($this->responseJson()['success']);
        $this->assertSessionNotHasKey('Auth.User');
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::login()
     */
    public function testCorrectPasswordClearsEarlierFailures(): void
    {
        $attempts = $this->getTableLocator()->get('LoginAttempts');
        $attempts->saveOrFail($attempts->newEntity([
            'identifier' => 'janedelacruz',
            'created' => new DateTime('-1 minute'),
        ]));

        $this->asAjax();
        $this->post('/api/login', ['user_name' => 'janedelacruz', 'user_pass' => self::PASSWORD]);

        $this->assertTrue($this->responseJson()['success']);
        $this->assertSame(0, $attempts->find()->where(['identifier' => 'janedelacruz'])->count());
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::session()
     */
    public function testSessionWithoutLoginReportsLoggedOut(): void
    {
        $this->get('/api/session');

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertFalse($body['loggedIn']);
        $this->assertNull($body['user']);
        $this->assertNull($body['reason']);
        $this->assertHeaderContains('Cache-Control', 'no-store');
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::session()
     */
    public function testSessionReportsLoggedInUser(): void
    {
        $this->loginAs(1);
        $this->get('/api/session');

        $this->assertResponseOk();
        $body = $this->responseJson();
        $this->assertTrue($body['loggedIn']);
        $this->assertSame(1, $body['user']['user_id']);
        $this->assertArrayNotHasKey('user_pass', $body['user']);
    }

    /**
     * Signing in on another device rotates session_token, which ends this session.
     *
     * @return void
     * @uses \App\Controller\UsersController::session()
     */
    public function testSessionEndsWhenAccountSignsInElsewhere(): void
    {
        $this->loginAs(1);

        $users = $this->getTableLocator()->get('Users');
        $user = $users->get(1);
        $user->session_token = 'token-from-another-device';
        $users->saveOrFail($user);

        $this->get('/api/session');

        $body = $this->responseJson();
        $this->assertFalse($body['loggedIn']);
        $this->assertSame('signed_in_elsewhere', $body['reason']);
        $this->assertSessionNotHasKey('Auth.User');
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::logout()
     */
    public function testLogoutEndsSession(): void
    {
        $this->loginAs(1);
        $this->asAjax();
        $this->post('/api/logout');

        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['success']);
        $this->assertSessionNotHasKey('Auth.User');
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::logout()
     */
    public function testLogoutWithoutAjaxHeaderIsForbidden(): void
    {
        $this->loginAs(1);
        $this->post('/api/logout');

        $this->assertResponseCode(403);
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::register()
     */
    public function testRegisterWithoutAjaxHeaderIsForbidden(): void
    {
        $this->post('/api/register', [
            'fname' => 'Juan',
            'lname' => 'Santos',
            'email' => 'juan@example.com',
            'user_name' => 'juansantos',
            'user_pass' => 'Password123',
        ]);

        $this->assertResponseCode(403);
        $this->assertSame(0, $this->getTableLocator()->get('Users')->find()
            ->where(['email' => 'juan@example.com'])->count());
    }

    /**
     * @return void
     * @uses \App\Controller\UsersController::updateAccount()
     */
    public function testUpdateAccountRequiresLogin(): void
    {
        $this->asAjax();
        $this->post('/api/account', ['fname' => 'Janet']);

        $this->assertResponseCode(401);
    }
}

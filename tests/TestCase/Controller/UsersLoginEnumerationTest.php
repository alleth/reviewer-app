<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\UsersController;
use App\Test\TestSuite\ApiTestTrait;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use ReflectionClassConstant;

/**
 * H5: a failed sign-in never reveals whether the account exists or how it
 * signs in. A Google-only account (no password), a wrong password and an
 * unknown name get the same answer, count toward the same limits, and run
 * the same bcrypt check.
 *
 * Fixture user 1 (janedelacruz / jane@example.com) is verified with a
 * password; some tests turn it into a Google-only account.
 */
class UsersLoginEnumerationTest extends TestCase
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
        'app.RateLimits',
        'app.Passes',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnv('RATE_LIMIT_MODE', null);
        $this->setEnv('RATE_LIMIT_IP_MODE', null);
        DateTime::setTestNow(new DateTime('2026-10-20 12:00:00'));
    }

    protected function tearDown(): void
    {
        DateTime::setTestNow(null);
        $this->restoreEnv();
        parent::tearDown();
    }

    /**
     * Makes user 1 a Google-only account: no password, a Google id.
     */
    private function makeGoogleOnly(): void
    {
        $users = $this->getTableLocator()->get('Users');
        $users->updateAll(['user_pass' => null, 'google_id' => 'google-sub-1'], ['user_id' => 1]);
    }

    /**
     * Signs in and returns the raw response body.
     */
    private function attempt(string $name, string $password): string
    {
        $this->asAjax();
        $this->post('/api/login', ['user_name' => $name, 'user_pass' => $password]);

        return (string)$this->_response->getBody();
    }

    public function testAGoogleOnlyAccountGetsTheSameAnswerAsAnyFailure(): void
    {
        $wrongPassword = $this->attempt('janedelacruz', 'NotThePassword1');
        $unknown = $this->attempt('nobody_here', 'NotThePassword1');

        $this->makeGoogleOnly();
        $byName = $this->attempt('janedelacruz', 'anything-at-all');
        $this->assertResponseCode(200);
        $byEmail = $this->attempt('Jane@Example.com', 'anything-at-all');

        $this->assertSame($wrongPassword, $unknown);
        $this->assertSame($wrongPassword, $byName, 'Google-only by username');
        $this->assertSame($wrongPassword, $byEmail, 'Google-only by email');
        $this->assertSame(
            ['success' => false, 'message' => 'Invalid username or password'],
            json_decode($byName, true),
        );
    }

    public function testNoAccountDetailsAreReturned(): void
    {
        $this->makeGoogleOnly();
        $body = $this->attempt('jane@example.com', 'anything-at-all');

        foreach (['needsGoogleSetup', 'account', 'jane@example.com', 'Jane', 'Dela Cruz', 'janedelacruz'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, $needle);
        }
    }

    public function testGoogleOnlyAttemptsCountLikeWrongPasswords(): void
    {
        $this->makeGoogleOnly();
        for ($i = 1; $i <= 5; $i++) {
            $this->attempt('janedelacruz', 'guess' . $i);
            $this->assertResponseCode(200, "attempt $i");
        }

        $this->attempt('jane@example.com', 'guess6');

        $this->assertResponseCode(429, 'The 6th attempt waits, exactly as for a wrong password or unknown name');
        $this->assertSame('rate_limited', $this->responseJson()['reason'] ?? null);
    }

    public function testAnUnknownNameIsThrottledTheSameWay(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->attempt('nobody_here', 'guess' . $i);
            $this->assertResponseCode(200, "attempt $i");
        }

        $this->attempt('nobody_here', 'guess6');

        $this->assertResponseCode(429);
    }

    public function testTheCorrectPasswordStillSignsIn(): void
    {
        $this->attempt('janedelacruz', 'Password123');

        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['success'] ?? false);
    }

    public function testTheDummyHashMatchesTheRealPasswordHashing(): void
    {
        $dummy = (string)(new ReflectionClassConstant(UsersController::class, 'DUMMY_PASSWORD_HASH'))->getValue();
        $real = password_hash('example', PASSWORD_DEFAULT);

        $this->assertSame(password_get_info($real), password_get_info($dummy), 'Same algorithm and cost, so the same time');
        $this->assertFalse(password_verify('', $dummy));
        $this->assertFalse(password_verify('anything-at-all', $dummy));
    }
}

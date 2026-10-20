<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Test\TestSuite\ApiTestTrait;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * H1: the paid-content endpoints use the same session check as every other
 * signed-in endpoint. A dead session gets a 401 with a `reason`; a live
 * session without a pass gets a 403 `no_active_pass`; writes stay 403.
 *
 * @uses \App\Controller\Api\AppController
 */
class ContentAccessTest extends TestCase
{
    use ApiTestTrait;
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Passes',
        'app.Topics',
        'app.Questions',
        'app.Choices',
        'app.TopicReviews',
    ];

    /** The three read endpoints the SPA calls. */
    private const ENDPOINTS = ['/api/topics', '/api/topics/1', '/api/questions/practice'];

    /**
     * @param array<string, mixed> $fields
     */
    private function givePass(array $fields = []): void
    {
        $passes = $this->getTableLocator()->get('Passes');
        $passes->saveOrFail($passes->newEntity($fields + [
            'user_id' => 1,
            'reviewer' => 'civil-service',
            'plan_id' => '30-day',
            'amount' => '149.00',
            'currency' => 'PHP',
            'external_id' => 'cp_test_' . bin2hex(random_bytes(6)),
            'status' => 'paid',
            'paid_at' => new DateTime('-1 day'),
            'expires_at' => new DateTime('+29 days'),
        ], ['accessibleFields' => ['*' => true]]));
    }

    private function getAsAjax(string $url): void
    {
        $this->asAjax();
        $this->get($url);
    }

    public function testWithoutASessionEveryEndpointIs401SignedOut(): void
    {
        foreach (self::ENDPOINTS as $url) {
            $this->getAsAjax($url);

            $this->assertResponseCode(401, $url);
            $this->assertSame('signed_out', $this->responseJson()['reason'] ?? null, $url);
        }
    }

    public function testASessionReplacedOnAnotherDeviceIs401AndIsCleared(): void
    {
        $this->givePass();
        foreach (self::ENDPOINTS as $url) {
            $this->loginAs(1);
            // Another device signs in: the account's token moves on.
            $users = $this->getTableLocator()->get('Users');
            $user = $users->get(1);
            $user->session_token = 'token-from-another-device';
            $users->saveOrFail($user);

            $this->getAsAjax($url);

            // The pass is active, but this session isn't the account's any more.
            $this->assertResponseCode(401, $url);
            $this->assertSame('signed_in_elsewhere', $this->responseJson()['reason'] ?? null, $url);
            $this->assertSame('You signed in on another device.', $this->responseJson()['message'] ?? null);
            $this->assertSessionNotHasKey('Auth.User');
        }
    }

    public function testASessionFromBeforeTokensExistedIsStillAllowed(): void
    {
        $this->givePass();
        // Neither the session nor the account has a token (pre-rollout).
        $user = $this->getTableLocator()->get('Users')->get(1);
        $this->session(['Auth' => ['User' => $user]]);

        $this->getAsAjax('/api/topics');

        $this->assertResponseOk();
    }

    public function testSignedInWithoutAPassIs403NoActivePassAndKeepsTheSession(): void
    {
        foreach (self::ENDPOINTS as $url) {
            $this->loginAs(1);

            $this->getAsAjax($url);

            $this->assertResponseCode(403, $url);
            $this->assertSame('no_active_pass', $this->responseJson()['reason'] ?? null, $url);
            $this->assertSessionHasKey('Auth.User');
        }
    }

    public function testExpiredAndSupersededPassesDoNotCount(): void
    {
        $this->givePass(['expires_at' => new DateTime('-1 minute')]);
        $this->givePass(['status' => 'superseded']);
        $this->givePass(['status' => 'pending', 'paid_at' => null, 'expires_at' => null]);
        $this->loginAs(1);

        $this->getAsAjax('/api/topics');

        $this->assertResponseCode(403);
        $this->assertSame('no_active_pass', $this->responseJson()['reason'] ?? null);
    }

    public function testAnActivePassGetsTheContent(): void
    {
        $this->givePass();
        foreach (self::ENDPOINTS as $url) {
            $this->loginAs(1);

            $this->getAsAjax($url);

            $this->assertResponseOk($url);
            $this->assertNotEmpty($this->responseJson()['data'] ?? null, $url);
        }
    }

    public function testThereAreNoWriteRoutes(): void
    {
        $this->givePass();
        $this->loginAs(1);
        Configure::write('debug', false);
        $topics = $this->getTableLocator()->get('Topics');
        $before = $topics->find()->count();

        foreach (
            [
                ['post', '/api/topics'],
                ['put', '/api/topics/1'],
                ['patch', '/api/topics/1'],
                ['delete', '/api/topics/1'],
            ] as [$method, $url]
        ) {
            $this->asAjax();
            $this->{$method}($url, ['name' => 'New topic']);

            $this->assertResponseCode(404, "$method $url");
        }
        $this->assertSame($before, $topics->find()->count(), 'Nothing was written');
    }

    public function testAnUnverifiedAccountIs401EvenWithAPass(): void
    {
        // B3-V backstop: no path should give an unverified account a session,
        // but if one exists it can't read content.
        $this->givePass();
        $this->loginAs(1);
        $this->getTableLocator()->get('Users')->updateAll(['email_verified_at' => null], ['user_id' => 1]);

        $this->getAsAjax('/api/topics');

        $this->assertResponseCode(401);
        $this->assertSame('signed_out', $this->responseJson()['reason'] ?? null);
        $this->assertSessionNotHasKey('Auth.User');
    }

    public function testASessionForADeletedAccountIs401(): void
    {
        $user = $this->getTableLocator()->get('Users')->get(1);
        $user->user_id = 999;
        $this->session(['Auth' => ['User' => $user, 'token' => 'whatever']]);

        $this->getAsAjax('/api/topics');

        $this->assertResponseCode(401);
        $this->assertSame('signed_out', $this->responseJson()['reason'] ?? null);
    }
}

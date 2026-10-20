<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Test\TestSuite\ApiTestTrait;
use Cake\Core\Configure;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * H6: only the routes the app uses exist (no fallbacks, skeleton pages or dead
 * controllers), every state-changing route needs the X-Requested-With header
 * except Xendit's webhook, and 404s aren't logged as errors (L12).
 */
class RoutesTest extends TestCase
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
        'app.Topics',
        'app.Questions',
        'app.Choices',
        'app.TopicReviews',
    ];

    /**
     * Every state-changing route, apart from the webhook.
     */
    private const POST_ROUTES = [
        '/api/login',
        '/api/register',
        '/api/logout',
        '/api/verify-email',
        '/api/verify-email/resend',
        '/api/google-login',
        '/api/account/setup',
        '/api/account/email',
        '/api/account',
        '/api/password/forgot',
        '/api/password/reset',
        '/api/password/change',
        '/api/checkout',
        '/api/questions/1/answer',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // The production error page (debug pages quote source lines).
        Configure::write('debug', false);
    }

    protected function tearDown(): void
    {
        $this->restoreEnv();
        parent::tearDown();
    }

    public function testOnlyTheIntendedRoutesExist(): void
    {
        $this->get('/api/session');

        $templates = array_values(array_unique(array_map(fn($route) => $route->template, Router::routes())));
        sort($templates);

        $this->assertSame([
            '/api/account',
            '/api/account/email',
            '/api/account/setup',
            '/api/billing/history',
            '/api/checkout',
            '/api/google-login',
            '/api/login',
            '/api/logout',
            '/api/password/change',
            '/api/password/forgot',
            '/api/password/reset',
            '/api/purchases',
            '/api/questions/practice',
            '/api/questions/{id}/answer',
            '/api/register',
            '/api/session',
            '/api/topics',
            '/api/topics/{id}',
            '/api/verify-email',
            '/api/verify-email/resend',
            '/api/xendit/webhook',
        ], $templates);
    }

    public function testOldFallbackAndSkeletonUrlsAre404(): void
    {
        foreach (['/', '/pages/home', '/users/session', '/users/index', '/payments/my-passes', '/api/questions'] as $url) {
            $this->asAjax();
            $this->get($url);
            $this->assertResponseCode(404, "GET $url");
        }
        foreach (['/users/login', '/payments/checkout', '/payments/webhook', '/api/topics'] as $url) {
            $this->asAjax();
            $this->post($url, []);
            $this->assertResponseCode(404, "POST $url");
        }
    }

    public function testEveryStateChangingRouteNeedsTheHeader(): void
    {
        foreach (self::POST_ROUTES as $url) {
            $this->post($url, []);

            $this->assertResponseCode(403, $url);
            $this->assertSame('Missing required request header', $this->responseJson()['message'] ?? null, $url);
        }
    }

    public function testTheWebhookNeedsNoHeaderButStillChecksItsToken(): void
    {
        $this->setEnv('XENDIT_WEBHOOK_TOKEN', 'the-real-token');

        $this->post('/api/xendit/webhook', ['external_id' => 'cp_x']);
        $this->assertResponseCode(401, 'No header: reaches the webhook, which refuses the missing token');

        $this->configRequest(['headers' => ['x-callback-token' => 'wrong']]);
        $this->post('/api/xendit/webhook', ['external_id' => 'cp_x']);
        $this->assertResponseCode(401);
    }

    public function testA404IsNotLoggedAsAnError(): void
    {
        Log::setConfig('test_404', ['className' => 'Array', 'levels' => ['warning', 'error', 'critical']]);
        try {
            $this->get('/wp-login.php');
            $this->assertResponseCode(404);
            $this->get('/api/nope');
            $this->assertResponseCode(404);

            $engine = Log::engine('test_404');
            $this->assertInstanceOf(ArrayLog::class, $engine);
            $this->assertSame([], $engine->read());
        } finally {
            Log::drop('test_404');
        }
    }
}

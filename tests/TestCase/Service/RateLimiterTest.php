<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\RateLimiter;
use App\Test\TestSuite\ApiTestTrait;
use Cake\I18n\DateTime;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;

/**
 * H4: sliding-window limits over `rate_limits`, HMAC keys, retention, and the
 * enforce / log / off modes (global and per-IP).
 *
 * @uses \App\Service\RateLimiter
 */
class RateLimiterTest extends TestCase
{
    use ApiTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = ['app.RateLimits'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnv('RATE_LIMIT_MODE', null);
        $this->setEnv('RATE_LIMIT_IP_MODE', null);
        DateTime::setTestNow(new DateTime('2026-10-18 12:00:00'));
        Log::setConfig('test_ratelimit', ['className' => 'Array', 'levels' => ['info'], 'scopes' => ['ratelimit']]);
    }

    protected function tearDown(): void
    {
        Log::drop('test_ratelimit');
        DateTime::setTestNow(null);
        $this->restoreEnv();
        parent::tearDown();
    }

    private function events(): Table
    {
        return $this->getTableLocator()->get('RateLimits');
    }

    private function advance(int $seconds): void
    {
        DateTime::setTestNow((new DateTime())->modify("+{$seconds} seconds"));
    }

    /**
     * @return list<string>
     */
    private function logged(): array
    {
        $engine = Log::engine('test_ratelimit');
        $this->assertInstanceOf(ArrayLog::class, $engine);

        return $engine->read();
    }

    public function testKeysAreHmacsNeverTheRawValue(): void
    {
        $key = RateLimiter::key('ip', '203.0.113.7');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
        $this->assertStringNotContainsString('203.0.113.7', $key);
        $this->assertNotSame(hash('sha256', 'ip:203.0.113.7'), $key, 'Keyed with the salt, not a plain hash');
        $this->assertNotSame($key, RateLimiter::key('id', '203.0.113.7'), 'The kind is part of the key');

        (new RateLimiter())->hit('test', $key);
        $row = $this->events()->find()->firstOrFail();
        $this->assertSame($key, $row->get('key_hash'));
    }

    public function testALimitRefusesAtMaxAndFreesUpAsTheWindowSlides(): void
    {
        $limiter = new RateLimiter();
        $key = RateLimiter::key('u', '1');

        for ($i = 0; $i < 3; $i++) {
            $this->assertNull($limiter->retryAfter('test', $key, [[3, 60]]));
            $limiter->hit('test', $key);
            $this->advance(10);
        }
        // Three events at 0 s, 10 s, 20 s; now 30 s: the first leaves at 60 s.
        $this->assertSame(30, $limiter->retryAfter('test', $key, [[3, 60]]));

        $this->advance(30);
        $this->assertNull($limiter->retryAfter('test', $key, [[3, 60]]));
    }

    public function testTheLongestWaitOfSeveralWindowsWins(): void
    {
        $limiter = new RateLimiter();
        $key = RateLimiter::key('u', '1');
        for ($i = 0; $i < 4; $i++) {
            $limiter->hit('test', $key);
            $this->advance(60);
        }

        // [2 per 10 min] waits until 8 min from now; [4 per day] much longer.
        $wait = $limiter->retryAfter('test', $key, [[2, 600], [4, 86400]]);
        $this->assertSame(86400 - 240, $wait);
    }

    public function testBucketsAndKeysAreSeparateAndClearForgetsOnlyOne(): void
    {
        $limiter = new RateLimiter();
        $a = RateLimiter::key('u', '1');
        $b = RateLimiter::key('u', '2');
        $limiter->hit('one', $a);
        $limiter->hit('one', $b);
        $limiter->hit('two', $a);

        $limiter->clear('one', $a);

        $this->assertSame(0, $limiter->count('one', $a, 3600));
        $this->assertSame(1, $limiter->count('one', $b, 3600));
        $this->assertSame(1, $limiter->count('two', $a, 3600));
    }

    public function testEventsOlderThanSevenDaysArePrunedOnWrite(): void
    {
        $this->events()->saveOrFail($this->events()->newEntity([
            'bucket' => 'old',
            'key_hash' => RateLimiter::key('u', '1'),
            'created' => (new DateTime())->modify('-8 days'),
        ]));
        $this->events()->saveOrFail($this->events()->newEntity([
            'bucket' => 'recent',
            'key_hash' => RateLimiter::key('u', '1'),
            'created' => (new DateTime())->modify('-6 days'),
        ]));

        (new RateLimiter())->hit('new', RateLimiter::key('u', '1'));

        $buckets = $this->events()->find()->all()->extract('bucket')->toList();
        sort($buckets);
        $this->assertSame(['new', 'recent'], $buckets);
    }

    public function testRefusalsAreLoggedWithAShortHashOnly(): void
    {
        $limiter = new RateLimiter();
        $key = RateLimiter::key('ip', '203.0.113.7');
        $limiter->hit('test', $key);

        $this->assertSame(60, $limiter->retryAfter('test', $key, [[1, 60]]));

        $lines = $this->logged();
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('[ratelimit] test ' . substr($key, 0, 12) . ': refused', $lines[0]);
        $this->assertStringNotContainsString('203.0.113.7', $lines[0]);
        $this->assertStringNotContainsString($key, $lines[0], 'Never the whole hash');
    }

    public function testLogModeCountsAndLogsButNeverRefuses(): void
    {
        $this->setEnv('RATE_LIMIT_MODE', 'log');
        $limiter = new RateLimiter();
        $key = RateLimiter::key('u', '1');
        $limiter->hit('test', $key);

        $this->assertNull($limiter->retryAfter('test', $key, [[1, 60]]));
        $this->assertSame(1, $limiter->count('test', $key, 60));
        $this->assertStringContainsString('would refuse (log mode)', $this->logged()[0]);
    }

    public function testOffModeNeitherCountsNorChecks(): void
    {
        $this->setEnv('RATE_LIMIT_MODE', 'off');
        $limiter = new RateLimiter();
        $key = RateLimiter::key('u', '1');
        $limiter->hit('test', $key);
        $limiter->hit('test', $key);

        $this->assertSame(0, $this->events()->find()->count());
        $this->assertNull($limiter->retryAfter('test', $key, [[1, 60]]));
        $this->assertSame([], $this->logged());
    }

    public function testPerIpLimitsAreLogOnlyUntilEnforcedAndNeverStricterThanGlobal(): void
    {
        $this->assertSame(RateLimiter::MODE_ENFORCE, RateLimiter::mode());
        $this->assertSame(RateLimiter::MODE_LOG, RateLimiter::mode(true), 'Per-IP defaults to log');

        $limiter = new RateLimiter();
        $key = RateLimiter::key('ip', '203.0.113.7');
        $limiter->hit('ip_test', $key, true);
        $this->assertNull($limiter->retryAfter('ip_test', $key, [[1, 60]], true));

        $this->setEnv('RATE_LIMIT_IP_MODE', 'enforce');
        $this->assertSame(60, $limiter->retryAfter('ip_test', $key, [[1, 60]], true));

        $this->setEnv('RATE_LIMIT_MODE', 'log');
        $this->assertSame(RateLimiter::MODE_LOG, RateLimiter::mode(true));
        $this->setEnv('RATE_LIMIT_MODE', 'off');
        $this->assertSame(RateLimiter::MODE_OFF, RateLimiter::mode(true));
    }

    public function testUnknownModeValuesFallBackToTheDefaults(): void
    {
        $this->setEnv('RATE_LIMIT_MODE', 'banana');
        $this->setEnv('RATE_LIMIT_IP_MODE', 'ENFORCE ');

        $this->assertSame(RateLimiter::MODE_ENFORCE, RateLimiter::mode());
        $this->assertSame(RateLimiter::MODE_ENFORCE, RateLimiter::mode(true), 'Case and spaces are ignored');
    }
}

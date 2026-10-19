<?php
declare(strict_types=1);

namespace App\Test\TestCase\Http;

use App\Http\ClientIp;
use App\Test\TestSuite\ApiTestTrait;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;

/**
 * H4: the client IP behind Railway's proxy is `X-Real-IP`, which Railway's
 * edge sets (and overwrites); `X-Forwarded-For` is never used by default.
 *
 * @uses \App\Http\ClientIp
 */
class ClientIpTest extends TestCase
{
    use ApiTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnv('RATE_LIMIT_IP_HEADER', null);
    }

    protected function tearDown(): void
    {
        $this->restoreEnv();
        parent::tearDown();
    }

    /**
     * @param array<string, string> $env
     */
    private function request(array $env): ServerRequest
    {
        return new ServerRequest(['environment' => $env + ['REMOTE_ADDR' => '100.64.0.7']]);
    }

    public function testXRealIpIsUsed(): void
    {
        $request = $this->request([
            'HTTP_X_REAL_IP' => ' 198.51.100.20 ',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.9',
        ]);

        $this->assertSame('198.51.100.20', ClientIp::from($request), 'Neither X-Forwarded-For entry is used');
    }

    public function testXForwardedForAloneIsIgnored(): void
    {
        $request = $this->request(['HTTP_X_FORWARDED_FOR' => '198.51.100.20, 203.0.113.9']);

        $this->assertSame('100.64.0.7', ClientIp::from($request));
    }

    public function testIpv6IsAccepted(): void
    {
        $request = $this->request(['HTTP_X_REAL_IP' => '2001:db8::1']);

        $this->assertSame('2001:db8::1', ClientIp::from($request));
    }

    public function testAnInvalidOrMissingValueFallsBackToRemoteAddr(): void
    {
        $this->assertSame('100.64.0.7', ClientIp::from($this->request([])));
        $this->assertSame('100.64.0.7', ClientIp::from($this->request(['HTTP_X_REAL_IP' => 'nonsense'])));
        $this->assertSame('unknown', ClientIp::from(new ServerRequest(['environment' => ['REMOTE_ADDR' => '']])));
    }

    public function testAnotherHeaderCanBeChosen(): void
    {
        $this->setEnv('RATE_LIMIT_IP_HEADER', 'X-Forwarded-For');
        $request = $this->request([
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 198.51.100.30',
            'HTTP_X_REAL_IP' => '198.51.100.20',
        ]);

        $this->assertSame('198.51.100.30', ClientIp::from($request), 'The rightmost entry of a list-valued header');
    }
}

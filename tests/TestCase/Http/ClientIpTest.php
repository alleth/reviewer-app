<?php
declare(strict_types=1);

namespace App\Test\TestCase\Http;

use App\Http\ClientIp;
use App\Test\TestSuite\ApiTestTrait;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;

/**
 * H4: the client IP behind Railway's proxy is the rightmost X-Forwarded-For
 * entry (the one the proxy appends), so a client can't choose it.
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

    public function testTheRightmostForwardedAddressIsUsed(): void
    {
        $request = $this->request(['HTTP_X_FORWARDED_FOR' => '198.51.100.20']);
        $this->assertSame('198.51.100.20', ClientIp::from($request));

        $request = $this->request(['HTTP_X_FORWARDED_FOR' => '1.2.3.4, 10.0.0.1,  198.51.100.20 ']);
        $this->assertSame('198.51.100.20', ClientIp::from($request), 'A faked value on the left is ignored');
    }

    public function testIpv6IsAccepted(): void
    {
        $request = $this->request(['HTTP_X_FORWARDED_FOR' => '2001:db8::1']);

        $this->assertSame('2001:db8::1', ClientIp::from($request));
    }

    public function testAnInvalidOrMissingValueFallsBackToRemoteAddr(): void
    {
        $this->assertSame('100.64.0.7', ClientIp::from($this->request([])));
        $this->assertSame('100.64.0.7', ClientIp::from($this->request(['HTTP_X_FORWARDED_FOR' => '1.2.3.4, nonsense'])));
        $this->assertSame('unknown', ClientIp::from(new ServerRequest(['environment' => ['REMOTE_ADDR' => '']])));
    }

    public function testAnotherHeaderCanBeChosen(): void
    {
        $this->setEnv('RATE_LIMIT_IP_HEADER', 'X-Real-IP');
        $request = $this->request([
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
            'HTTP_X_REAL_IP' => '198.51.100.30',
        ]);

        $this->assertSame('198.51.100.30', ClientIp::from($request));
    }

    public function testDiagnoseReportsHeaderShapeAndSource(): void
    {
        $request = $this->request([
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 198.51.100.20',
            'HTTP_X_REAL_IP' => '198.51.100.20',
        ]);
        $this->assertSame([
            'xff' => true,
            'xff_entries' => 2,
            'x_real_ip' => true,
            'x_envoy_external_address' => false,
            'xff_left_equals_real_ip' => false,
            'xff_right_is_internal' => false,
            'remote_addr_fallback' => false,
            'source' => 'xff_rightmost',
        ], ClientIp::diagnose($request));

        $railwayShape = $this->request([
            'HTTP_X_FORWARDED_FOR' => '198.51.100.20, 100.64.0.5',
            'HTTP_X_REAL_IP' => '198.51.100.20',
        ]);
        $d = ClientIp::diagnose($railwayShape);
        $this->assertTrue($d['xff_left_equals_real_ip']);
        $this->assertTrue($d['xff_right_is_internal'], '100.64.0.0/10 is internal');
        foreach (['10.1.2.3', '192.168.0.1', '127.0.0.1', 'fd00::1'] as $internal) {
            $hop = $this->request(['HTTP_X_FORWARDED_FOR' => "198.51.100.20, $internal"]);
            $this->assertTrue(ClientIp::diagnose($hop)['xff_right_is_internal'], $internal);
        }
        $this->assertFalse(
            ClientIp::diagnose($this->request(['HTTP_X_FORWARDED_FOR' => '100.128.0.1']))['xff_right_is_internal'],
            'Just outside 100.64.0.0/10',
        );

        $d = ClientIp::diagnose($this->request(['HTTP_X_ENVOY_EXTERNAL_ADDRESS' => '198.51.100.40']));
        $this->assertSame(0, $d['xff_entries']);
        $this->assertTrue($d['x_envoy_external_address']);
        $this->assertTrue($d['remote_addr_fallback']);
        $this->assertSame('remote_addr', $d['source']);

        $this->setEnv('RATE_LIMIT_IP_HEADER', 'X-Real-IP');
        $this->assertSame('custom_header', ClientIp::diagnose($request)['source']);
    }

    public function testDiagnoseNeverContainsAnAddress(): void
    {
        $request = $this->request([
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 198.51.100.20',
            'HTTP_X_REAL_IP' => '198.51.100.21',
            'HTTP_X_ENVOY_EXTERNAL_ADDRESS' => '2001:db8::77',
        ]);
        $flat = json_encode(ClientIp::diagnose($request));

        foreach (['203.0', '198.51', '100.64', '2001:db8', '.9', '::77'] as $needle) {
            $this->assertStringNotContainsString($needle, (string)$flat);
        }
    }
}

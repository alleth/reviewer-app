<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\AjaxHeaderMiddleware;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * H6: the CSRF check — every state-changing request needs
 * `X-Requested-With: XMLHttpRequest`, except Xendit's webhook.
 */
class AjaxHeaderMiddlewareTest extends TestCase
{
    /**
     * Runs the middleware; returns its response, or 'passed' when it handed
     * the request on.
     *
     * @param array<string, string> $headers
     */
    private function through(string $method, string $path, array $headers = []): ResponseInterface|string
    {
        $request = new ServerRequest(['url' => $path, 'environment' => ['REQUEST_METHOD' => $method]]);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;

                return new Response();
            }
        };

        $response = (new AjaxHeaderMiddleware())->process($request, $handler);

        return $handler->called ? 'passed' : $response;
    }

    public function testSafeMethodsNeedNoHeader(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $this->assertSame('passed', $this->through($method, '/api/session'), $method);
        }
    }

    public function testStateChangingMethodsNeedTheHeader(): void
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            foreach (['/api/login', '/api/topics/1', '/anything/else'] as $path) {
                $response = $this->through($method, $path);

                $this->assertInstanceOf(ResponseInterface::class, $response, "$method $path");
                $this->assertSame(403, $response->getStatusCode(), "$method $path");
                $this->assertSame(
                    ['success' => false, 'message' => 'Missing required request header'],
                    json_decode((string)$response->getBody(), true),
                );
                $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
            }
        }
    }

    public function testTheHeaderLetsTheRequestThrough(): void
    {
        $this->assertSame('passed', $this->through('POST', '/api/login', ['X-Requested-With' => 'XMLHttpRequest']));
        $this->assertSame('passed', $this->through('DELETE', '/api/x', ['X-Requested-With' => 'XMLHttpRequest']));
    }

    public function testOnlyTheExactHeaderValueCounts(): void
    {
        foreach (['', 'fetch', 'xmlhttprequest-ish'] as $value) {
            $response = $this->through('POST', '/api/login', ['X-Requested-With' => $value]);
            $this->assertInstanceOf(ResponseInterface::class, $response, "'$value'");
            $this->assertSame(403, $response->getStatusCode());
        }
    }

    public function testOnlyTheXenditWebhookIsExempt(): void
    {
        $this->assertSame('passed', $this->through('POST', '/api/xendit/webhook'));
        $this->assertSame('passed', $this->through('POST', '/api/xendit/webhook/'));

        foreach (['/api/xendit/webhook/x', '/api/xendit', '/x/api/xendit/webhook', '/payments/webhook'] as $path) {
            $response = $this->through('POST', $path);
            $this->assertInstanceOf(ResponseInterface::class, $response, $path);
            $this->assertSame(403, $response->getStatusCode(), $path);
        }
    }
}

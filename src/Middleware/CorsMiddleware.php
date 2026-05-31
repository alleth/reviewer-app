<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    private string $allowedOrigin;

    public function __construct()
    {
        $this->allowedOrigin = env('FRONTEND_URL', 'http://localhost:3000');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Short-circuit preflight — no need to hit routing or controllers
        if ($request->getMethod() === 'OPTIONS') {
            return (new Response())
                ->withStatus(200)
                ->withHeader('Access-Control-Allow-Origin', $this->allowedOrigin)
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization')
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $handler->handle($request)
            ->withHeader('Access-Control-Allow-Origin', $this->allowedOrigin)
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}

<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The app's CSRF check (H6): every state-changing request (anything but GET,
 * HEAD or OPTIONS) must carry `X-Requested-With: XMLHttpRequest`, whatever
 * route it is for, so a new action can't forget it.
 *
 * A plain HTML <form> POST — the classic CSRF vector — can't set custom
 * headers, and a cross-origin fetch/XHR that does triggers a CORS preflight,
 * which CorsMiddleware only allows for FRONTEND_URL. The session cookie is also
 * SameSite=Lax. The Xendit webhook is the one exemption: it is called by
 * Xendit's servers and authenticates with its callback token.
 *
 * Controllers still call requireAjaxHeader() as a second layer.
 */
class AjaxHeaderMiddleware implements MiddlewareInterface
{
    /** Methods that never change state, so need no header. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** Paths (relative to the app's base) exempt from the check. */
    private const EXEMPT_PATHS = ['/api/xendit/webhook'];

    /**
     * A 403 for a state-changing request without the header; otherwise passes
     * the request on.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The next handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (
            in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)
            || in_array(rtrim($request->getUri()->getPath(), '/'), self::EXEMPT_PATHS, true)
            || $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest'
        ) {
            return $handler->handle($request);
        }

        return (new Response())
            ->withStatus(403)
            ->withType('application/json')
            ->withStringBody((string)json_encode(['success' => false, 'message' => 'Missing required request header']));
    }
}

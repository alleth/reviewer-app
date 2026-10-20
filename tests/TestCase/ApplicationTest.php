<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.3.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Test\TestCase;

use App\Application;
use App\Middleware\AjaxHeaderMiddleware;
use App\Middleware\CorsMiddleware;
use Cake\Core\Configure;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * ApplicationTest class
 */
class ApplicationTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Test bootstrap in production.
     *
     * @return void
     */
    public function testBootstrap()
    {
        Configure::write('debug', false);
        $app = new Application(dirname(__DIR__, 2) . '/config');
        $app->bootstrap();
        $plugins = $app->getPlugins();

        $this->assertTrue($plugins->has('Bake'), 'plugins has Bake?');
        $this->assertFalse($plugins->has('DebugKit'), 'plugins has DebugKit?');
        $this->assertTrue($plugins->has('Migrations'), 'plugins has Migrations?');
    }

    /**
     * Test bootstrap add DebugKit plugin in debug mode.
     *
     * @return void
     */
    public function testBootstrapInDebug()
    {
        Configure::write('debug', true);
        $app = new Application(dirname(__DIR__, 2) . '/config');
        $app->bootstrap();
        $plugins = $app->getPlugins();

        $this->assertTrue($plugins->has('DebugKit'), 'plugins has DebugKit?');
    }

    /**
     * testMiddleware
     *
     * CorsMiddleware is added first (before ErrorHandlerMiddleware) so preflight
     * OPTIONS requests are short-circuited before routing — see CLAUDE.md
     * "CORS & Middleware". Then AjaxHeaderMiddleware, the CSRF check (H6), so
     * its 403 still gets CORS headers. No CsrfProtectionMiddleware: the header
     * check replaces Cake's token scheme.
     *
     * @return void
     */
    public function testMiddleware()
    {
        $app = new Application(dirname(__DIR__, 2) . '/config');
        $middleware = new MiddlewareQueue();

        $middleware = $app->middleware($middleware);

        $this->assertInstanceOf(CorsMiddleware::class, $middleware->current());
        $middleware->seek(1);
        $this->assertInstanceOf(AjaxHeaderMiddleware::class, $middleware->current());
        $middleware->seek(2);
        $this->assertInstanceOf(ErrorHandlerMiddleware::class, $middleware->current());
        $middleware->seek(3);
        $this->assertInstanceOf(AssetMiddleware::class, $middleware->current());
        $middleware->seek(4);
        $this->assertInstanceOf(RoutingMiddleware::class, $middleware->current());
    }

    /**
     * The session cookie is SameSite=Lax (H6, S13): the SPA and the API are
     * same-site, and browsers then keep it off cross-site POSTs.
     *
     * @return void
     */
    public function testSessionCookieIsSameSiteLax()
    {
        $this->assertSame('Lax', Configure::read('Session.ini')['session.cookie_samesite'] ?? null);
        $this->assertSame(1, Configure::read('Session.ini')['session.cookie_secure'] ?? null);
        $this->assertSame(1, Configure::read('Session.ini')['session.cookie_httponly'] ?? null);
    }
}

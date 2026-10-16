<?php
declare(strict_types=1);

namespace App;

use App\Auth\GoogleTokenVerifier;
use App\Auth\GoogleTokenVerifierInterface;
use App\Middleware\CorsMiddleware;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;

class Application extends BaseApplication
{
    /**
     * @inheritDoc
     */
    public function bootstrap(): void
    {
        parent::bootstrap();

        if (PHP_SAPI !== 'cli') {
            FactoryLocator::add('Table', (new TableLocator())->allowFallbackClass(false));
        }
    }

    /**
     * The middleware queue. Order matters: see CLAUDE.md (CORS & Middleware).
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The queue to fill.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            ->add(new CorsMiddleware())
            ->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))
            ->add(new RoutingMiddleware($this))
            ->add(new BodyParserMiddleware());

        return $middlewareQueue;
    }

    /**
     * Services for dependency injection into controllers and commands.
     *
     * @param \Cake\Core\ContainerInterface $container The container to register in.
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        // Injected into UsersController::googleLogin(); tests replace it with mockService().
        $container->add(GoogleTokenVerifierInterface::class, GoogleTokenVerifier::class);
    }
}

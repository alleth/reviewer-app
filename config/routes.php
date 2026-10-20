<?php

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    // Auth routes (UsersController in root namespace)
    $routes->connect('/api/login', ['controller' => 'Users', 'action' => 'login']);
    $routes->connect('/api/register', ['controller' => 'Users', 'action' => 'register']);
    $routes->connect('/api/logout', ['controller' => 'Users', 'action' => 'logout']);
    $routes->connect('/api/session', ['controller' => 'Users', 'action' => 'session']);
    $routes->connect('/api/verify-email', ['controller' => 'Users', 'action' => 'verifyEmail']);
    $routes->connect('/api/verify-email/resend', ['controller' => 'Users', 'action' => 'resendVerification']);
    $routes->connect('/api/google-login', ['controller' => 'Users', 'action' => 'googleLogin']);
    $routes->connect('/api/account/setup', ['controller' => 'Users', 'action' => 'setupAccount']);
    $routes->connect('/api/account/email', ['controller' => 'Users', 'action' => 'changeEmail']);
    $routes->connect('/api/account', ['controller' => 'Users', 'action' => 'updateAccount']);
    $routes->connect('/api/password/forgot', ['controller' => 'Users', 'action' => 'forgotPassword']);
    $routes->connect('/api/password/reset', ['controller' => 'Users', 'action' => 'resetPassword']);
    $routes->connect('/api/password/change', ['controller' => 'Users', 'action' => 'changePassword']);

    // Payments (PaymentsController in root namespace — manual auth like Users)
    $routes->connect('/api/checkout', ['controller' => 'Payments', 'action' => 'checkout']);
    $routes->connect('/api/purchases', ['controller' => 'Payments', 'action' => 'myPasses']);
    $routes->connect('/api/billing/history', ['controller' => 'Payments', 'action' => 'history']);
    $routes->connect('/api/xendit/webhook', ['controller' => 'Payments', 'action' => 'webhook']);

    // API resource routes (controllers in App\Controller\Api namespace)
    $routes->prefix('api', function (RouteBuilder $builder): void {
        // Questions are only reachable as practice sets and one-at-a-time answer
        // checks (H2): there is deliberately no index/view, so the bank and its
        // answer key can't be listed.
        $builder->connect('/questions/practice', ['controller' => 'Questions', 'action' => 'practice']);
        $builder->connect(
            '/questions/{id}/answer',
            ['controller' => 'Questions', 'action' => 'answer'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        // Read-only: content comes from bin/cake import_questions, so there are
        // no write routes (H6).
        $builder->resources('Topics', ['only' => ['index', 'view']]);
    });

    // Nothing else is routed: no fallbacks() and no skeleton pages (H6), so a
    // controller method is reachable only through a route listed above. Any
    // other URL is a 404.
};

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
    $routes->connect('/api/google-login', ['controller' => 'Users', 'action' => 'googleLogin']);
    $routes->connect('/api/account/setup', ['controller' => 'Users', 'action' => 'setupAccount']);
    $routes->connect('/api/account', ['controller' => 'Users', 'action' => 'updateAccount']);
    $routes->connect('/api/password/forgot', ['controller' => 'Users', 'action' => 'forgotPassword']);
    $routes->connect('/api/password/reset', ['controller' => 'Users', 'action' => 'resetPassword']);
    $routes->connect('/api/password/change', ['controller' => 'Users', 'action' => 'changePassword']);

    // API resource routes (controllers in App\Controller\Api namespace)
    $routes->prefix('api', function (RouteBuilder $builder): void {
        // Custom action must be defined before resources() to avoid 'practice' being matched as an ID
        $builder->connect('/questions/practice', ['controller' => 'Questions', 'action' => 'practice']);
        $builder->resources('Topics');
        $builder->resources('Questions');
    });

    // Default routes
    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);
        $builder->connect('/pages/*', 'Pages::display');
        $builder->fallbacks();
    });
};

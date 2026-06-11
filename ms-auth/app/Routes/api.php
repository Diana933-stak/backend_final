<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/api', function (RouteCollectorProxy $group): void {
        $group->post('/login', [AuthController::class, 'login']);
        $group->post('/logout', [AuthController::class, 'logout'])->add(AuthMiddleware::class);
        $group->get('/validar-sesion', [AuthController::class, 'validarSesion'])->add(AuthMiddleware::class);
    });
};

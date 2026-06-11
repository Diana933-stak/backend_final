<?php

declare(strict_types=1);

use App\Controllers\ProgramacionViajeController;
use App\Controllers\RutaController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/api', function (RouteCollectorProxy $api): void {
        $api->get('/rutas', [RutaController::class, 'index']);
        $api->post('/rutas', [RutaController::class, 'store']);
        $api->get('/rutas/{id:[0-9]+}', [RutaController::class, 'show']);
        $api->put('/rutas/{id:[0-9]+}', [RutaController::class, 'update']);
        $api->patch('/rutas/{id:[0-9]+}', [RutaController::class, 'update']);
        $api->delete('/rutas/{id:[0-9]+}', [RutaController::class, 'destroy']);

        $api->get('/programaciones-viajes', [ProgramacionViajeController::class, 'index']);
        $api->post('/programaciones-viajes', [ProgramacionViajeController::class, 'store']);
        $api->get('/programaciones-viajes/{id:[0-9]+}', [ProgramacionViajeController::class, 'show']);
        $api->put('/programaciones-viajes/{id:[0-9]+}', [ProgramacionViajeController::class, 'update']);
        $api->patch('/programaciones-viajes/{id:[0-9]+}', [ProgramacionViajeController::class, 'update']);
        $api->patch('/programaciones-viajes/{id:[0-9]+}/estado', [ProgramacionViajeController::class, 'updateEstado']);
        $api->delete('/programaciones-viajes/{id:[0-9]+}', [ProgramacionViajeController::class, 'destroy']);
    })->add(AuthMiddleware::class);
};

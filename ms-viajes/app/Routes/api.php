<?php

declare(strict_types=1);

use App\Controllers\ViajeController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/api', function (RouteCollectorProxy $api): void {
        $api->get('/viajes/historial', [ViajeController::class, 'historial']);
        $api->post('/viajes/{programacion_id:[0-9]+}/iniciar', [ViajeController::class, 'iniciar']);
        $api->patch('/viajes/{programacion_id:[0-9]+}/estado', [ViajeController::class, 'actualizarEstado']);
        $api->post('/viajes/{programacion_id:[0-9]+}/novedades', [ViajeController::class, 'registrarNovedad']);
        $api->post('/viajes/{programacion_id:[0-9]+}/finalizar', [ViajeController::class, 'finalizar']);
        $api->get('/viajes/{programacion_id:[0-9]+}/seguimiento', [ViajeController::class, 'seguimiento']);
    })->add(AuthMiddleware::class);
};

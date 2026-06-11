<?php

declare(strict_types=1);

use App\Controllers\VehiculoController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/api', function (RouteCollectorProxy $api): void {
        $api->get('/vehiculos/placa/{placa}', [VehiculoController::class, 'byPlaca']);
        $api->get('/vehiculos/estado/{estado}', [VehiculoController::class, 'byEstado']);
        $api->get('/vehiculos/tipo/{tipo}', [VehiculoController::class, 'byTipo']);
        $api->get('/vehiculos', [VehiculoController::class, 'index']);
        $api->post('/vehiculos', [VehiculoController::class, 'store']);
        $api->get('/vehiculos/{id:[0-9]+}', [VehiculoController::class, 'show']);
        $api->put('/vehiculos/{id:[0-9]+}', [VehiculoController::class, 'update']);
        $api->patch('/vehiculos/{id:[0-9]+}', [VehiculoController::class, 'update']);
        $api->delete('/vehiculos/{id:[0-9]+}', [VehiculoController::class, 'destroy']);
    })->add(AuthMiddleware::class);
};

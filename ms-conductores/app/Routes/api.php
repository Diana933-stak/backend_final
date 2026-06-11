<?php

declare(strict_types=1);

use App\Controllers\ConductorController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/api', function (RouteCollectorProxy $api): void {
        $api->get('/conductores/documento/{documento}', [ConductorController::class, 'byDocumento']);
        $api->get('/conductores/licencia/{licencia}', [ConductorController::class, 'byLicencia']);
        $api->get('/conductores/estado/{estado}', [ConductorController::class, 'byEstado']);
        $api->get('/conductores', [ConductorController::class, 'index']);
        $api->post('/conductores', [ConductorController::class, 'store']);
        $api->get('/conductores/{id:[0-9]+}', [ConductorController::class, 'show']);
        $api->put('/conductores/{id:[0-9]+}', [ConductorController::class, 'update']);
        $api->patch('/conductores/{id:[0-9]+}', [ConductorController::class, 'update']);
        $api->delete('/conductores/{id:[0-9]+}', [ConductorController::class, 'destroy']);
    })->add(AuthMiddleware::class);
};

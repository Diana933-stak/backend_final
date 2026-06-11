<?php

declare(strict_types=1);

use App\Config\Database;
use App\Middleware\CorsMiddleware;
use App\Support\ApiResponse;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
date_default_timezone_set('America/Bogota');
Database::boot();
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(CorsMiddleware::class);
$app->options('/{routes:.*}', static fn (ServerRequestInterface $request, Response $response): Response => $response);
$app->get('/', static fn (ServerRequestInterface $request, Response $response) => ApiResponse::success($response, ['service' => 'ms-rutas'], 'Microservicio de rutas activo'));
(require $root . '/app/Routes/api.php')($app);
$errors = $app->addErrorMiddleware(false, true, true);
$errors->setDefaultErrorHandler(static function (ServerRequestInterface $request, \Throwable $exception) {
    $status = $exception instanceof HttpNotFoundException ? 404 : 500;
    return ApiResponse::error(new Response(), $status === 404 ? 'Endpoint no encontrado' : 'Error interno del servidor', $status)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Auth-Token')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
});
$app->run();

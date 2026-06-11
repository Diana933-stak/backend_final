<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\Usuario;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->token($request);
        if ($token === null) {
            return ApiResponse::error(new Response(), 'Token de autenticacion requerido', 401);
        }

        try {
            $usuario = Usuario::query()
                ->where('token', $token)
                ->where('sesion_activa', true)
                ->where('estado', 'activo')
                ->first();

            if ($usuario === null) {
                return ApiResponse::error(new Response(), 'Sesion invalida o expirada', 401);
            }

            return $handler->handle($request->withAttribute('usuario', $usuario));
        } catch (\Throwable $exception) {
            return ApiResponse::error(new Response(), 'No fue posible validar la sesion', 500);
        }
    }

    private function token(ServerRequestInterface $request): ?string
    {
        $authorization = trim($request->getHeaderLine('Authorization'));
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }
        $token = trim($request->getHeaderLine('X-Auth-Token'));
        return $token !== '' ? $token : null;
    }
}

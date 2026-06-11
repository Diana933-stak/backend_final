<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\HttpClient;
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
            $url = 'http://127.0.0.1:8001/api/validar-sesion';
            $result = (new HttpClient())->request('GET', $url, $token);
            if ($result['status'] !== 200 || !is_array($result['body']) || ($result['body']['success'] ?? false) !== true) {
                return ApiResponse::error(new Response(), $result['status'] === 0 ? 'Servicio de autenticacion no disponible' : 'Sesion invalida o expirada', $result['status'] === 0 ? 503 : 401);
            }
            return $handler->handle($request->withAttribute('usuario', $result['body']['data']['usuario'] ?? null)->withAttribute('auth_token', $token));
        } catch (\Throwable $exception) {
            return ApiResponse::error(new Response(), 'Servicio de autenticacion no disponible', 503);
        }
    }

    private function token(ServerRequestInterface $request): ?string
    {
        if (preg_match('/^Bearer\s+(.+)$/i', trim($request->getHeaderLine('Authorization')), $matches) === 1) {
            return trim($matches[1]);
        }
        $token = trim($request->getHeaderLine('X-Auth-Token'));
        return $token !== '' ? $token : null;
    }
}

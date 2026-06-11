<?php

declare(strict_types=1);

namespace App\Middleware;

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
        $url = 'http://127.0.0.1:8001/api/validar-sesion';
        $context = stream_context_create(['http' => ['method' => 'GET', 'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n", 'ignore_errors' => true, 'timeout' => 5]]);
        try {
            $body = @file_get_contents($url, false, $context);
            $status = $this->status($http_response_header ?? []);
            $payload = is_string($body) ? json_decode($body, true) : null;
            if ($status !== 200 || !is_array($payload) || ($payload['success'] ?? false) !== true) {
                return ApiResponse::error(new Response(), $status === 0 ? 'Servicio de autenticacion no disponible' : 'Sesion invalida o expirada', $status === 0 ? 503 : 401);
            }
            return $handler->handle($request->withAttribute('usuario', $payload['data']['usuario'] ?? null)->withAttribute('auth_token', $token));
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

    private function status(array $headers): int
    {
        return isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $matches) === 1 ? (int) $matches[1] : 0;
    }
}

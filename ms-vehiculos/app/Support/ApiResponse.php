<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface;

final class ApiResponse
{
    public static function success(ResponseInterface $response, mixed $data = null, string $message = 'Operacion exitosa', int $status = 200): ResponseInterface
    {
        return self::json($response, ['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(ResponseInterface $response, string $message, int $status = 400, mixed $errors = null): ResponseInterface
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        return self::json($response, $payload, $status);
    }

    private static function json(ResponseInterface $response, array $payload, int $status): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
    }
}

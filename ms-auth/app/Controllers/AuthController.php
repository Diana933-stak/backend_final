<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Usuario;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = (array) ($request->getParsedBody() ?? []);
            $identificador = trim((string) ($data['identificador'] ?? $data['usuario'] ?? $data['correo'] ?? ''));
            $contrasena = (string) ($data['contrasena'] ?? $data['password'] ?? '');

            $errors = [];
            if ($identificador === '') {
                $errors['identificador'] = 'El usuario o correo es obligatorio';
            }
            if ($contrasena === '') {
                $errors['contrasena'] = 'La contrasena es obligatoria';
            }
            if ($errors !== []) {
                return ApiResponse::error($response, 'Datos de acceso invalidos', 422, $errors);
            }

            $usuario = Usuario::query()
                ->where('usuario', $identificador)
                ->orWhere('correo', $identificador)
                ->first();

            if ($usuario === null || !$this->validPassword($contrasena, (string) $usuario->contrasena)) {
                return ApiResponse::error($response, 'Credenciales incorrectas', 401);
            }
            if ($usuario->estado !== 'activo') {
                return ApiResponse::error($response, 'El usuario se encuentra inactivo', 403);
            }

            $token = bin2hex(random_bytes(32));
            $usuario->forceFill(['token' => $token, 'sesion_activa' => true])->save();

            return ApiResponse::success($response, [
                'token' => $token,
                'usuario' => $this->userData($usuario),
            ], 'Inicio de sesion exitoso');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible iniciar sesion', 500);
        }
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            /** @var Usuario $usuario */
            $usuario = $request->getAttribute('usuario');
            $usuario->forceFill(['token' => null, 'sesion_activa' => false])->save();
            return ApiResponse::success($response, null, 'Sesion cerrada correctamente');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible cerrar la sesion', 500);
        }
    }

    public function validarSesion(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var Usuario $usuario */
        $usuario = $request->getAttribute('usuario');
        return ApiResponse::success($response, ['usuario' => $this->userData($usuario)], 'Sesion valida');
    }

    private function validPassword(string $plain, string $stored): bool
    {
        $info = password_get_info($stored);
        return $info['algo'] !== null && $info['algo'] !== 0
            ? password_verify($plain, $stored)
            : hash_equals($stored, $plain);
    }

    private function userData(Usuario $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'correo' => $usuario->correo,
            'usuario' => $usuario->usuario,
            'rol' => $usuario->rol,
            'estado' => $usuario->estado,
        ];
    }
}

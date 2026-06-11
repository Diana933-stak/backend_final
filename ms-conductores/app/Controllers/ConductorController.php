<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Conductor;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ConductorController
{
    private const ESTADOS = ['disponible', 'en_ruta', 'inactivo'];

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $query = Conductor::query();
            foreach (['documento', 'numero_licencia', 'estado'] as $field) {
                if (isset($params[$field]) && trim((string) $params[$field]) !== '') {
                    $query->where($field, trim((string) $params[$field]));
                }
            }
            $items = $query->orderBy('id', 'desc')->get();
            return ApiResponse::success($response, $items, 'Conductores consultados');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible consultar los conductores', 500);
        }
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->findBy($response, 'id', $args['id'], 'Conductor no encontrado');
    }

    public function byDocumento(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->findBy($response, 'documento', $args['documento'], 'Conductor no encontrado');
    }

    public function byLicencia(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->findBy($response, 'numero_licencia', $args['licencia'], 'Licencia no encontrada');
    }

    public function byEstado(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            if (!in_array($args['estado'], self::ESTADOS, true)) {
                return ApiResponse::error($response, 'Estado invalido', 422);
            }
            return ApiResponse::success($response, Conductor::query()->where('estado', $args['estado'])->orderBy('id')->get(), 'Conductores consultados');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible consultar los conductores', 500);
        }
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $this->normalize((array) ($request->getParsedBody() ?? []));
            $errors = $this->validate($data, null, false);
            if ($errors !== []) {
                return ApiResponse::error($response, 'Datos del conductor invalidos', 422, $errors);
            }
            $conductor = Conductor::query()->create($data);
            return ApiResponse::success($response, $conductor, 'Conductor creado correctamente', 201);
        } catch (QueryException $exception) {
            return ApiResponse::error($response, 'Documento, correo o licencia ya registrados', 409);
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible crear el conductor', 500);
        }
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $conductor = Conductor::query()->find($args['id']);
            if ($conductor === null) {
                return ApiResponse::error($response, 'Conductor no encontrado', 404);
            }
            $data = $this->normalize((array) ($request->getParsedBody() ?? []), true);
            if ($data === []) {
                return ApiResponse::error($response, 'No se enviaron campos para actualizar', 422);
            }
            $errors = $this->validate($data, (int) $conductor->id, true);
            if ($errors !== []) {
                return ApiResponse::error($response, 'Datos del conductor invalidos', 422, $errors);
            }
            $conductor->fill($data)->save();
            return ApiResponse::success($response, $conductor->fresh(), 'Conductor actualizado correctamente');
        } catch (QueryException $exception) {
            return ApiResponse::error($response, 'Documento, correo o licencia ya registrados', 409);
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible actualizar el conductor', 500);
        }
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $conductor = Conductor::query()->find($args['id']);
            if ($conductor === null) {
                return ApiResponse::error($response, 'Conductor no encontrado', 404);
            }
            $conductor->delete();
            return ApiResponse::success($response, null, 'Conductor eliminado correctamente');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible eliminar el conductor', 500);
        }
    }

    private function findBy(ResponseInterface $response, string $field, mixed $value, string $notFound): ResponseInterface
    {
        try {
            $conductor = Conductor::query()->where($field, $value)->first();
            return $conductor === null
                ? ApiResponse::error($response, $notFound, 404)
                : ApiResponse::success($response, $conductor, 'Conductor consultado');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible consultar el conductor', 500);
        }
    }

    private function normalize(array $data, bool $partial = false): array
    {
        $allowed = ['nombres', 'apellidos', 'documento', 'telefono', 'correo', 'numero_licencia', 'categoria_licencia', 'fecha_vencimiento_licencia', 'estado'];
        $result = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $result[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }
        if (!$partial && !isset($result['estado'])) {
            $result['estado'] = 'disponible';
        }
        if (isset($result['correo'])) {
            $result['correo'] = strtolower((string) $result['correo']);
        }
        return $result;
    }

    private function validate(array $data, ?int $ignoreId, bool $partial): array
    {
        $errors = [];
        $required = ['nombres', 'apellidos', 'documento', 'telefono', 'correo', 'numero_licencia', 'categoria_licencia', 'fecha_vencimiento_licencia'];
        foreach ($required as $field) {
            if ((!$partial || array_key_exists($field, $data)) && trim((string) ($data[$field] ?? '')) === '') {
                $errors[$field] = 'Este campo es obligatorio';
            }
        }
        if (isset($data['correo']) && filter_var($data['correo'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['correo'] = 'El correo no es valido';
        }
        if (isset($data['estado']) && !in_array($data['estado'], self::ESTADOS, true)) {
            $errors['estado'] = 'Estado invalido';
        }
        if (isset($data['fecha_vencimiento_licencia'])) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data['fecha_vencimiento_licencia']);
            if ($date === false || $date->format('Y-m-d') !== $data['fecha_vencimiento_licencia']) {
                $errors['fecha_vencimiento_licencia'] = 'La fecha debe usar el formato YYYY-MM-DD';
            }
        }
        foreach (['documento', 'correo', 'numero_licencia'] as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                continue;
            }
            $query = Conductor::query()->where($field, $data[$field]);
            if ($ignoreId !== null) {
                $query->where('id', '<>', $ignoreId);
            }
            if ($query->exists()) {
                $errors[$field] = 'El valor ya se encuentra registrado';
            }
        }
        return $errors;
    }
}

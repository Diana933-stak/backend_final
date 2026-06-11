<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Ruta;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RutaController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $query = Ruta::query();
            if (!empty($params['origen'])) {
                $query->where('ciudad_origen', trim((string) $params['origen']));
            }
            if (!empty($params['destino'])) {
                $query->where('ciudad_destino', trim((string) $params['destino']));
            }
            return ApiResponse::success($response, $query->orderBy('id', 'desc')->get(), 'Rutas consultadas');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible consultar las rutas', 500);
        }
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $ruta = Ruta::query()->find($args['id']);
            return $ruta === null ? ApiResponse::error($response, 'Ruta no encontrada', 404) : ApiResponse::success($response, $ruta, 'Ruta consultada');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible consultar la ruta', 500);
        }
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $this->normalize((array) ($request->getParsedBody() ?? []));
            $errors = $this->validate($data, null, false);
            if ($errors !== []) {
                return ApiResponse::error($response, 'Datos de la ruta invalidos', 422, $errors);
            }
            return ApiResponse::success($response, Ruta::query()->create($data), 'Ruta creada correctamente', 201);
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible crear la ruta', 500);
        }
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $ruta = Ruta::query()->find($args['id']);
            if ($ruta === null) {
                return ApiResponse::error($response, 'Ruta no encontrada', 404);
            }
            $data = $this->normalize((array) ($request->getParsedBody() ?? []), true);
            if ($data === []) {
                return ApiResponse::error($response, 'No se enviaron campos para actualizar', 422);
            }
            $merged = array_merge($ruta->only(['ciudad_origen', 'ciudad_destino', 'distancia', 'tiempo_estimado', 'observaciones']), $data);
            $errors = $this->validate($merged, (int) $ruta->id, false);
            if ($errors !== []) {
                return ApiResponse::error($response, 'Datos de la ruta invalidos', 422, $errors);
            }
            $ruta->fill($data)->save();
            return ApiResponse::success($response, $ruta->fresh(), 'Ruta actualizada correctamente');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible actualizar la ruta', 500);
        }
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $ruta = Ruta::query()->find($args['id']);
            if ($ruta === null) {
                return ApiResponse::error($response, 'Ruta no encontrada', 404);
            }
            if ($ruta->programaciones()->exists()) {
                return ApiResponse::error($response, 'No se puede eliminar una ruta con viajes programados', 409);
            }
            $ruta->delete();
            return ApiResponse::success($response, null, 'Ruta eliminada correctamente');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible eliminar la ruta', 500);
        }
    }

    private function normalize(array $data, bool $partial = false): array
    {
        $result = [];
        foreach (['ciudad_origen', 'ciudad_destino', 'distancia', 'tiempo_estimado', 'observaciones'] as $field) {
            if (array_key_exists($field, $data)) {
                $result[$field] = is_string($data[$field]) ? trim((string) preg_replace('/\s+/', ' ', $data[$field])) : $data[$field];
            }
        }
        return $result;
    }

    private function validate(array $data, ?int $ignoreId, bool $partial): array
    {
        $errors = [];
        foreach (['ciudad_origen', 'ciudad_destino', 'distancia', 'tiempo_estimado'] as $field) {
            if ((!$partial || array_key_exists($field, $data)) && trim((string) ($data[$field] ?? '')) === '') {
                $errors[$field] = 'Este campo es obligatorio';
            }
        }
        if (isset($data['distancia']) && (!is_numeric($data['distancia']) || (float) $data['distancia'] <= 0)) {
            $errors['distancia'] = 'La distancia debe ser mayor que cero';
        }
        if (!empty($data['ciudad_origen']) && !empty($data['ciudad_destino'])) {
            if (strcasecmp($data['ciudad_origen'], $data['ciudad_destino']) === 0) {
                $errors['ciudad_destino'] = 'El origen y destino deben ser diferentes';
            }
            $query = Ruta::query()->where('ciudad_origen', $data['ciudad_origen'])->where('ciudad_destino', $data['ciudad_destino']);
            if ($ignoreId !== null) {
                $query->where('id', '<>', $ignoreId);
            }
            if ($query->exists()) {
                $errors['ruta'] = 'La ruta ya se encuentra registrada';
            }
        }
        return $errors;
    }
}

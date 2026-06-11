<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProgramacionViaje;
use App\Models\Ruta;
use App\Services\ResourceService;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class ProgramacionViajeController
{
    private const ESTADOS = ['programado', 'en_transito', 'retrasado', 'finalizado', 'cancelado'];
    private const ACTIVOS = ['programado', 'en_transito', 'retrasado'];

    public function __construct(private ?ResourceService $resources = null)
    {
        $this->resources ??= new ResourceService();
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $query = ProgramacionViaje::query()->with('ruta');
            $filters = [
                'conductor_id' => $params['conductor_id'] ?? $params['conductor'] ?? null,
                'vehiculo_id' => $params['vehiculo_id'] ?? $params['vehiculo'] ?? null,
                'fecha_salida' => $params['fecha_salida'] ?? $params['fecha'] ?? null,
                'estado' => $params['estado'] ?? null,
                'ruta_id' => $params['ruta_id'] ?? null,
            ];
            foreach ($filters as $field => $value) {
                if ($value !== null && trim((string) $value) !== '') {
                    $query->where($field, trim((string) $value));
                }
            }
            return ApiResponse::success($response, $query->orderBy('fecha_salida', 'desc')->orderBy('hora_salida', 'desc')->get(), 'Programaciones consultadas');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible consultar las programaciones', 500);
        }
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $programacion = ProgramacionViaje::query()->with('ruta')->find($args['id']);
            return $programacion === null
                ? ApiResponse::error($response, 'Programacion no encontrada', 404)
                : ApiResponse::success($response, $programacion, 'Programacion consultada');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible consultar la programacion', 500);
        }
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $this->normalize((array) ($request->getParsedBody() ?? []));
            $errors = $this->validate($data, null, false);
            if ($errors !== []) {
                return ApiResponse::error($response, 'Datos de programacion invalidos', 422, $errors);
            }
            $resourceError = $this->validateResources($data, (string) $request->getAttribute('auth_token'));
            if ($resourceError !== null) {
                return ApiResponse::error($response, $resourceError['message'], $resourceError['status']);
            }
            $programacion = ProgramacionViaje::query()->create($data);
            return ApiResponse::success($response, $programacion->load('ruta'), 'Viaje programado correctamente', 201);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($response, $exception->getMessage(), $this->runtimeStatus($exception));
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible crear la programacion', 500);
        }
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $programacion = ProgramacionViaje::query()->find($args['id']);
            if ($programacion === null) {
                return ApiResponse::error($response, 'Programacion no encontrada', 404);
            }
            if (in_array($programacion->estado, ['finalizado', 'cancelado'], true)) {
                return ApiResponse::error($response, 'No se puede modificar una programacion finalizada o cancelada', 409);
            }
            $data = $this->normalize((array) ($request->getParsedBody() ?? []), true);
            unset($data['estado']);
            if ($data === []) {
                return ApiResponse::error($response, 'No se enviaron campos para actualizar', 422);
            }
            $current = $programacion->only(['conductor_id', 'vehiculo_id', 'ruta_id', 'fecha_salida', 'hora_salida', 'fecha_estimada_llegada', 'observaciones', 'estado']);
            $merged = array_merge($current, $data);
            $errors = $this->validate($merged, (int) $programacion->id, false);
            if ($errors !== []) {
                return ApiResponse::error($response, 'Datos de programacion invalidos', 422, $errors);
            }
            $resourceError = $this->validateResources($merged, (string) $request->getAttribute('auth_token'));
            if ($resourceError !== null) {
                return ApiResponse::error($response, $resourceError['message'], $resourceError['status']);
            }
            $programacion->fill($data)->save();
            return ApiResponse::success($response, $programacion->fresh()->load('ruta'), 'Programacion actualizada correctamente');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($response, $exception->getMessage(), $this->runtimeStatus($exception));
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible actualizar la programacion', 500);
        }
    }

    public function updateEstado(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $programacion = ProgramacionViaje::query()->find($args['id']);
            if ($programacion === null) {
                return ApiResponse::error($response, 'Programacion no encontrada', 404);
            }
            $data = (array) ($request->getParsedBody() ?? []);
            $estado = trim((string) ($data['estado'] ?? ''));
            if (!in_array($estado, self::ESTADOS, true)) {
                return ApiResponse::error($response, 'Estado invalido', 422);
            }
            if (in_array($programacion->estado, ['finalizado', 'cancelado'], true) && $estado !== $programacion->estado) {
                return ApiResponse::error($response, 'El estado actual es definitivo', 409);
            }
            $programacion->estado = $estado;
            $programacion->save();
            return ApiResponse::success($response, $programacion->fresh()->load('ruta'), 'Estado de programacion actualizado');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible actualizar el estado', 500);
        }
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $programacion = ProgramacionViaje::query()->find($args['id']);
            if ($programacion === null) {
                return ApiResponse::error($response, 'Programacion no encontrada', 404);
            }
            if (in_array($programacion->estado, ['en_transito', 'retrasado', 'finalizado'], true)) {
                return ApiResponse::error($response, 'No se puede eliminar esta programacion', 409);
            }
            $programacion->delete();
            return ApiResponse::success($response, null, 'Programacion eliminada correctamente');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible eliminar la programacion', 500);
        }
    }

    private function normalize(array $data, bool $partial = false): array
    {
        $result = [];
        foreach (['conductor_id', 'vehiculo_id', 'ruta_id', 'fecha_salida', 'hora_salida', 'fecha_estimada_llegada', 'observaciones', 'estado'] as $field) {
            if (array_key_exists($field, $data)) {
                $result[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }
        if (!$partial && !isset($result['estado'])) {
            $result['estado'] = 'programado';
        }
        return $result;
    }

    private function validate(array $data, ?int $ignoreId, bool $partial): array
    {
        $errors = [];
        foreach (['conductor_id', 'vehiculo_id', 'ruta_id', 'fecha_salida', 'hora_salida', 'fecha_estimada_llegada'] as $field) {
            if ((!$partial || array_key_exists($field, $data)) && trim((string) ($data[$field] ?? '')) === '') {
                $errors[$field] = 'Este campo es obligatorio';
            }
        }
        foreach (['conductor_id', 'vehiculo_id', 'ruta_id'] as $field) {
            if (isset($data[$field]) && filter_var($data[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $errors[$field] = 'Debe ser un identificador valido';
            }
        }
        foreach (['fecha_salida', 'fecha_estimada_llegada'] as $field) {
            if (isset($data[$field]) && !$this->validDate((string) $data[$field])) {
                $errors[$field] = 'La fecha debe usar el formato YYYY-MM-DD';
            }
        }
        if (isset($data['hora_salida']) && !$this->validTime((string) $data['hora_salida'])) {
            $errors['hora_salida'] = 'La hora debe usar el formato HH:MM o HH:MM:SS';
        }
        if (!empty($data['fecha_salida']) && !empty($data['fecha_estimada_llegada']) && $data['fecha_estimada_llegada'] < $data['fecha_salida']) {
            $errors['fecha_estimada_llegada'] = 'La llegada no puede ser anterior a la salida';
        }
        if (isset($data['estado']) && !in_array($data['estado'], self::ESTADOS, true)) {
            $errors['estado'] = 'Estado invalido';
        }
        if (!empty($data['ruta_id']) && !Ruta::query()->whereKey($data['ruta_id'])->exists()) {
            $errors['ruta_id'] = 'La ruta no existe';
        }
        if (empty($errors['fecha_salida']) && !empty($data['fecha_salida'])) {
            foreach (['conductor_id', 'vehiculo_id'] as $field) {
                if (empty($data[$field])) {
                    continue;
                }
                $query = ProgramacionViaje::query()->where($field, $data[$field])->where('fecha_salida', $data['fecha_salida'])->whereIn('estado', self::ACTIVOS);
                if ($ignoreId !== null) {
                    $query->where('id', '<>', $ignoreId);
                }
                if ($query->exists()) {
                    $errors[$field] = $field === 'conductor_id' ? 'El conductor ya tiene un viaje activo para esta fecha' : 'El vehiculo ya tiene un viaje activo para esta fecha';
                }
            }
        }
        return $errors;
    }

    private function validateResources(array $data, string $token): ?array
    {
        $conductor = $this->resources->conductor((int) $data['conductor_id'], $token);
        if (($conductor['estado'] ?? null) !== 'disponible') {
            return ['message' => 'El conductor no se encuentra disponible', 'status' => 409];
        }
        $vehiculo = $this->resources->vehiculo((int) $data['vehiculo_id'], $token);
        if (($vehiculo['estado'] ?? null) === 'mantenimiento') {
            return ['message' => 'No se puede programar un vehiculo en mantenimiento', 'status' => 409];
        }
        if (($vehiculo['estado'] ?? null) !== 'disponible') {
            return ['message' => 'El vehiculo no se encuentra disponible', 'status' => 409];
        }
        return null;
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validTime(string $value): bool
    {
        foreach (['!H:i', '!H:i:s'] as $format) {
            $time = \DateTimeImmutable::createFromFormat($format, $value);
            if ($time !== false && $time->format(strlen($value) === 5 ? 'H:i' : 'H:i:s') === $value) {
                return true;
            }
        }
        return false;
    }

    private function runtimeStatus(RuntimeException $exception): int
    {
        return in_array($exception->getCode(), [404, 503], true) ? $exception->getCode() : 503;
    }
}

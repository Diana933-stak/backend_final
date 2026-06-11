<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SeguimientoViaje;
use App\Services\LogisticsService;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class ViajeController
{
    private const ESTADOS = ['programado', 'en_transito', 'retrasado', 'finalizado', 'cancelado'];

    public function __construct(private ?LogisticsService $logistics = null)
    {
        $this->logistics ??= new LogisticsService();
    }

    public function iniciar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $id = (int) $args['programacion_id'];
            $token = (string) $request->getAttribute('auth_token');
            $programacion = $this->logistics->programacion($id, $token);
            if (($programacion['estado'] ?? null) === 'cancelado') {
                return ApiResponse::error($response, 'No se puede iniciar un viaje cancelado', 409);
            }
            if (($programacion['estado'] ?? null) === 'finalizado') {
                return ApiResponse::error($response, 'El viaje ya se encuentra finalizado', 409);
            }
            if (($programacion['estado'] ?? null) !== 'programado') {
                return ApiResponse::error($response, 'El viaje ya fue iniciado', 409);
            }
            $programacion = $this->logistics->updateProgramacionEstado($id, 'en_transito', $token);
            $data = (array) ($request->getParsedBody() ?? []);
            $seguimiento = $this->record($id, 'en_transito', $this->optionalText($data['novedad'] ?? 'Inicio del viaje'));
            $warnings = $this->logistics->syncResources($programacion, 'en_ruta', $token);
            return ApiResponse::success($response, [
                'programacion' => $programacion,
                'seguimiento' => $seguimiento,
                'advertencias' => $warnings,
            ], 'Viaje iniciado correctamente');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($response, $exception->getMessage(), $this->runtimeStatus($exception));
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible iniciar el viaje', 500);
        }
    }

    public function actualizarEstado(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $data = (array) ($request->getParsedBody() ?? []);
            $estado = trim((string) ($data['estado'] ?? ''));
            if (!in_array($estado, self::ESTADOS, true)) {
                return ApiResponse::error($response, 'Estado invalido', 422);
            }
            if ($estado === 'finalizado') {
                return $this->finalizar($request, $response, $args);
            }

            $id = (int) $args['programacion_id'];
            $token = (string) $request->getAttribute('auth_token');
            $programacion = $this->logistics->programacion($id, $token);
            $actual = (string) ($programacion['estado'] ?? '');
            $allowed = [
                'programado' => ['cancelado'],
                'en_transito' => ['en_transito', 'retrasado', 'cancelado'],
                'retrasado' => ['retrasado', 'en_transito', 'cancelado'],
                'finalizado' => [],
                'cancelado' => [],
            ];
            if (!in_array($estado, $allowed[$actual] ?? [], true)) {
                return ApiResponse::error($response, 'Transicion de estado no permitida', 409);
            }
            $programacion = $this->logistics->updateProgramacionEstado($id, $estado, $token);
            $seguimiento = $this->record($id, $estado, $this->optionalText($data['novedad'] ?? null));
            $warnings = $estado === 'cancelado' ? $this->logistics->syncResources($programacion, 'disponible', $token) : [];
            return ApiResponse::success($response, ['programacion' => $programacion, 'seguimiento' => $seguimiento, 'advertencias' => $warnings], 'Estado del viaje actualizado');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($response, $exception->getMessage(), $this->runtimeStatus($exception));
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible actualizar el viaje', 500);
        }
    }

    public function registrarNovedad(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $data = (array) ($request->getParsedBody() ?? []);
            $novedad = trim((string) ($data['novedad'] ?? ''));
            if ($novedad === '') {
                return ApiResponse::error($response, 'La novedad es obligatoria', 422, ['novedad' => 'Este campo es obligatorio']);
            }
            $id = (int) $args['programacion_id'];
            $programacion = $this->logistics->programacion($id, (string) $request->getAttribute('auth_token'));
            $estado = (string) ($programacion['estado'] ?? '');
            if (in_array($estado, ['finalizado', 'cancelado'], true)) {
                return ApiResponse::error($response, 'No se pueden registrar novedades en un viaje cerrado', 409);
            }
            $seguimiento = $this->record($id, $estado, $novedad);
            return ApiResponse::success($response, $seguimiento, 'Novedad registrada correctamente', 201);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($response, $exception->getMessage(), $this->runtimeStatus($exception));
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible registrar la novedad', 500);
        }
    }

    public function finalizar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $id = (int) $args['programacion_id'];
            $token = (string) $request->getAttribute('auth_token');
            $programacion = $this->logistics->programacion($id, $token);
            $estado = (string) ($programacion['estado'] ?? '');
            if ($estado === 'cancelado') {
                return ApiResponse::error($response, 'No se puede finalizar un viaje cancelado', 409);
            }
            if ($estado === 'finalizado') {
                return ApiResponse::error($response, 'El viaje ya se encuentra finalizado', 409);
            }
            $started = SeguimientoViaje::query()->where('programacion_viaje_id', $id)->whereIn('estado', ['en_transito', 'retrasado'])->exists();
            if (!$started || !in_array($estado, ['en_transito', 'retrasado'], true)) {
                return ApiResponse::error($response, 'No se puede finalizar un viaje que no ha sido iniciado', 409);
            }
            $programacion = $this->logistics->updateProgramacionEstado($id, 'finalizado', $token);
            $data = (array) ($request->getParsedBody() ?? []);
            $seguimiento = $this->record($id, 'finalizado', $this->optionalText($data['novedad'] ?? 'Viaje finalizado'));
            $warnings = $this->logistics->syncResources($programacion, 'disponible', $token);
            return ApiResponse::success($response, ['programacion' => $programacion, 'seguimiento' => $seguimiento, 'advertencias' => $warnings], 'Viaje finalizado correctamente');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($response, $exception->getMessage(), $this->runtimeStatus($exception));
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible finalizar el viaje', 500);
        }
    }

    public function seguimiento(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $id = (int) $args['programacion_id'];
            $programacion = $this->logistics->programacion($id, (string) $request->getAttribute('auth_token'));
            $registros = SeguimientoViaje::query()->where('programacion_viaje_id', $id)->orderBy('fecha')->orderBy('hora')->orderBy('id')->get();
            return ApiResponse::success($response, ['programacion' => $programacion, 'seguimiento' => $registros], 'Seguimiento consultado');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($response, $exception->getMessage(), $this->runtimeStatus($exception));
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible consultar el seguimiento', 500);
        }
    }

    public function historial(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $query = SeguimientoViaje::query();
            if (!empty($params['programacion_viaje_id'])) {
                $query->where('programacion_viaje_id', (int) $params['programacion_viaje_id']);
            }
            if (!empty($params['estado']) && in_array($params['estado'], self::ESTADOS, true)) {
                $query->where('estado', $params['estado']);
            }
            if (!empty($params['fecha_desde'])) {
                $query->where('fecha', '>=', $params['fecha_desde']);
            }
            if (!empty($params['fecha_hasta'])) {
                $query->where('fecha', '<=', $params['fecha_hasta']);
            }
            return ApiResponse::success($response, $query->orderBy('fecha', 'desc')->orderBy('hora', 'desc')->orderBy('id', 'desc')->get(), 'Historial consultado');
        } catch (\Throwable $exception) {
            return ApiResponse::error($response, 'No fue posible consultar el historial', 500);
        }
    }

    private function record(int $id, string $estado, ?string $novedad): SeguimientoViaje
    {
        $now = new \DateTimeImmutable('now');
        return SeguimientoViaje::query()->create([
            'programacion_viaje_id' => $id,
            'fecha' => $now->format('Y-m-d'),
            'hora' => $now->format('H:i:s'),
            'estado' => $estado,
            'novedad' => $novedad,
        ]);
    }

    private function optionalText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function runtimeStatus(RuntimeException $exception): int
    {
        return in_array($exception->getCode(), [404, 409, 503], true) ? $exception->getCode() : 503;
    }
}

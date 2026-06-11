<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class LogisticsService
{
    public function __construct(private ?HttpClient $client = null)
    {
        $this->client ??= new HttpClient();
    }

    public function programacion(int $id, string $token): array
    {
        $url = 'http://127.0.0.1:8004/api/programaciones-viajes/' . $id;
        return $this->successful($this->client->request('GET', $url, $token), 'Programacion no encontrada');
    }

    public function updateProgramacionEstado(int $id, string $estado, string $token): array
    {
        $url = 'http://127.0.0.1:8004/api/programaciones-viajes/' . $id . '/estado';
        return $this->successful($this->client->request('PATCH', $url, $token, ['estado' => $estado]), 'No fue posible actualizar la programacion');
    }

    public function syncResources(array $programacion, string $estado, string $token): array
    {
        $warnings = [];
        $targets = [
            ['url' => 'http://127.0.0.1:8002/api/conductores/' . (int) $programacion['conductor_id'], 'name' => 'conductor'],
            ['url' => 'http://127.0.0.1:8003/api/vehiculos/' . (int) $programacion['vehiculo_id'], 'name' => 'vehiculo'],
        ];
        foreach ($targets as $target) {
            $result = $this->client->request('PATCH', $target['url'], $token, ['estado' => $estado]);
            if ($result['status'] !== 200) {
                $warnings[] = 'No se pudo sincronizar el estado del ' . $target['name'];
            }
        }
        return $warnings;
    }

    private function successful(array $result, string $notFoundMessage): array
    {
        if ($result['status'] === 404) {
            throw new RuntimeException($notFoundMessage, 404);
        }
        if ($result['status'] < 200 || $result['status'] >= 300 || !is_array($result['body']) || ($result['body']['success'] ?? false) !== true) {
            $message = is_array($result['body']) ? (string) ($result['body']['message'] ?? 'Servicio de rutas no disponible') : 'Servicio de rutas no disponible';
            throw new RuntimeException($message, $result['status'] === 409 ? 409 : 503);
        }
        return (array) ($result['body']['data'] ?? []);
    }
}

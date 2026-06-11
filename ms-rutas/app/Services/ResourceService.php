<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ResourceService
{
    public function __construct(private ?HttpClient $client = null)
    {
        $this->client ??= new HttpClient();
    }

    public function conductor(int $id, string $token): array
    {
        return $this->fetch('http://127.0.0.1:8002/api/conductores/' . $id, $token, 'conductor');
    }

    public function vehiculo(int $id, string $token): array
    {
        return $this->fetch('http://127.0.0.1:8003/api/vehiculos/' . $id, $token, 'vehiculo');
    }

    private function fetch(string $url, string $token, string $resource): array
    {
        $result = $this->client->request('GET', $url, $token);
        if ($result['status'] === 404) {
            throw new RuntimeException(ucfirst($resource) . ' no encontrado', 404);
        }
        if ($result['status'] !== 200 || !is_array($result['body']) || ($result['body']['success'] ?? false) !== true) {
            throw new RuntimeException('No fue posible validar el ' . $resource, 503);
        }
        return (array) ($result['body']['data'] ?? []);
    }
}

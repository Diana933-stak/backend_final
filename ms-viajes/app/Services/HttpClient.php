<?php

declare(strict_types=1);

namespace App\Services;

final class HttpClient
{
    public function request(string $method, string $url, string $token, ?array $data = null): array
    {
        $headers = ["Authorization: Bearer {$token}", 'Accept: application/json'];
        $content = '';
        if ($data !== null) {
            $headers[] = 'Content-Type: application/json';
            $content = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $context = stream_context_create(['http' => [
            'method' => strtoupper($method), 'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $content, 'ignore_errors' => true, 'timeout' => 5,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $status = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches) === 1 ? (int) $matches[1] : 0;
        return ['status' => $status, 'body' => is_string($body) ? json_decode($body, true) : null];
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir)) ?: '/';
        }

        $uri = preg_replace('#^/public#', '', $uri) ?: '/';
        return '/' . trim($uri, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function only(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $this->input($key);
        }

        return $data;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function ip(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trustedProxies = (array) config('security.trusted_proxies', []);

        // Only honor X-Forwarded-For when the immediate connection is a configured
        // reverse proxy/load balancer - otherwise it's a client-supplied header an
        // attacker can spoof to rotate their apparent IP and defeat rate limiting.
        if ($trustedProxies === [] || !in_array($remoteAddr, $trustedProxies, true)) {
            return $remoteAddr;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        return $forwarded !== '' ? trim(explode(',', $forwarded)[0]) : $remoteAddr;
    }
}

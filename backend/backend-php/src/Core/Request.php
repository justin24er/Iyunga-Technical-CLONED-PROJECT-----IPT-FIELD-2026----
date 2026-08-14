<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thin wrapper around PHP's superglobals so controllers never touch
 * $_GET / $_POST / php://input directly. Makes unit testing and reasoning
 * about a single request's data much simpler.
 */
final class Request
{
    private string $method;
    private string $path;

    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,mixed> */
    private array $body;

    /** @var array<string,string> */
    private array $headers;

    /** @var array<string,string> */
    private array $cookies;

    /** @var array<string,string> route params, filled in by the router */
    private array $params = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = rtrim((string) parse_url($uri, PHP_URL_PATH), '/');
        if ($this->path === '') {
            $this->path = '/';
        }

        $this->query = $_GET ?? [];
        $this->cookies = $_COOKIE ?? [];
        $this->headers = self::collectHeaders();
        $this->body = self::parseBody($this->method, $this->headers);
    }

    private static function collectHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $raw = getallheaders() ?: [];
            $normalized = [];
            foreach ($raw as $k => $v) {
                $normalized[strtolower($k)] = $v;
            }
            return $normalized;
        }

        // Fallback for SAPIs without getallheaders() (e.g. php-fpm + nginx
        // without the pecl extension).
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    private static function parseBody(string $method, array $headers): array
    {
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return [];
        }

        $contentType = $headers['content-type'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            return $_POST ?? [];
        }

        $raw = file_get_contents('php://input') ?: '';
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function allInput(): array
    {
        return $this->body;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    public function ip(): ?string
    {
        $forwarded = $this->header('x-forwarded-for');
        if ($forwarded) {
            // May be a comma-separated chain; first entry is the client.
            return trim(explode(',', $forwarded)[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}

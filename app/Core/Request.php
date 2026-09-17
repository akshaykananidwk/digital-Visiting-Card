<?php

declare(strict_types=1);

namespace App\Core;

/** Immutable-ish wrapper over the PHP superglobals. */
final class Request
{
    private static ?Request $current = null;

    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,mixed> */
    private array $body;

    /** @var array<string,mixed> */
    private array $files;

    /** @var array<string,string> */
    private array $server;

    /** @var array<string,string> */
    private array $routeParams = [];

    private ?string $rawBody = null;

    public function __construct()
    {
        $this->query  = $_GET;
        $this->body   = $_POST;
        $this->files  = $_FILES;
        $this->server = array_map(static fn ($v) => is_string($v) ? $v : (string) $v, $_SERVER);

        if ($this->body === [] && str_contains($this->header('Content-Type', ''), 'application/json')) {
            $decoded = json_decode($this->rawBody(), true);
            if (is_array($decoded)) {
                $this->body = $decoded;
            }
        }
    }

    public static function capture(): Request
    {
        return self::$current = new self();
    }

    public static function current(): ?Request
    {
        return self::$current;
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST') {
            $override = strtoupper((string) ($this->body['_method'] ?? ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    /** Request path relative to the application base directory, always "/..." */
    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = explode('?', $uri, 2)[0];
        $uri = rawurldecode($uri);

        $base = Url::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . trim($uri, '/');

        return $uri === '//' ? '/' : $uri;
    }

    public function fullUrl(): string
    {
        return Url::to($this->path()) . ($this->query === [] ? '' : '?' . http_build_query($this->query));
    }

    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? '') !== '' && strtolower($this->server['HTTPS']) !== 'off') {
            return true;
        }
        if (strtolower($this->header('X-Forwarded-Proto', '')) === 'https') {
            return true;
        }

        return (int) ($this->server['SERVER_PORT'] ?? 80) === 443;
    }

    public function host(): string
    {
        $host = $this->header('X-Forwarded-Host', '') ?: ($this->server['HTTP_HOST'] ?? ($this->server['SERVER_NAME'] ?? 'localhost'));
        $host = explode(',', $host)[0];

        return strtolower(trim($host));
    }

    public function header(string $name, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$key])) {
            return $this->server[$key];
        }
        $alt = strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$alt])) {
            return $this->server[$alt];
        }

        return $default;
    }

    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = (string) file_get_contents('php://input');
        }

        return $this->rawBody;
    }

    public function ip(): string
    {
        // Only trust proxy headers when explicitly enabled (default: off).
        if ((bool) Env::get('TRUST_PROXY', false)) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $key) {
                if (!empty($this->server[$key])) {
                    $candidate = trim(explode(',', $this->server[$key])[0]);
                    if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                        return $candidate;
                    }
                }
            }
        }
        $ip = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr($this->header('User-Agent', ''), 0, 500);
    }

    public function referer(): string
    {
        return substr($this->header('Referer', ''), 0, 500);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        if (is_array($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    /** @return array<mixed> */
    public function array(string $key): array
    {
        $value = $this->input($key, []);

        return is_array($value) ? $value : [];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);

        return $value !== null && $value !== '' && $value !== [];
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * @param array<int,string> $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if ($this->has($key)) {
                $result[$key] = $this->input($key);
            }
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function query(): array
    {
        return $this->query;
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    public function hasFile(string $key): bool
    {
        return $this->file($key) !== null;
    }

    /** @return array<string,mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        if ($this->isAjax()) {
            return true;
        }
        $accept = $this->header('Accept', '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        return str_starts_with($this->path(), '/api/');
    }

    /** @param array<string,string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /** @return array<string,string> */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function route(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }
}

<?php

namespace App\Framework;

class Request
{
    private static ?self $instance = null;

    public function __construct(
        private readonly string $method,
        private readonly array $query,
        private readonly array $body,
        private readonly array $headers
    ) {
    }

    public static function capture(): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        $rawBody = file_get_contents('php://input') ?: '';
        $body = [];

        if (str_contains($contentType, 'application/json') && $rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            $body = is_array($decoded) ? $decoded : [];
        } elseif (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['PUT', 'PATCH', 'DELETE'], true)) {
            parse_str($rawBody, $body);
        } else {
            $body = $_POST;
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];

        self::$instance = new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $_GET,
            is_array($body) ? $body : [],
            is_array($headers) ? $headers : []
        );

        return self::$instance;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function body(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function bearerToken(): ?string
    {
        $authorization = $this->header('Authorization');
        if ($authorization === null || !str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        return trim(substr($authorization, 7));
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return is_array($value) ? null : (string)$value;
            }
        }

        return null;
    }
}

<?php

namespace App\Framework;

class JsonResponse
{
    public static function success(mixed $data, int $status = 200, array $meta = []): void
    {
        self::headers();
        http_response_code($status);

        $payload = [
            'status' => 'success',
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status = 400, array $errors = []): void
    {
        self::headers();
        http_response_code($status);

        $payload = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function empty(int $status = 204): void
    {
        self::headers();
        http_response_code($status);
        exit;
    }

    private static function headers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    }
}

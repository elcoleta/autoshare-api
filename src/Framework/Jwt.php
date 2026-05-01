<?php

namespace App\Framework;

use App\Exceptions\UnauthorizedException;

class Jwt
{
    public static function encode(array $payload, int $ttl = 28800): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttl;

        $headerPart = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadPart = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', "{$headerPart}.{$payloadPart}", self::secret(), true);

        return "{$headerPart}.{$payloadPart}." . self::base64UrlEncode($signature);
    }

    public static function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new UnauthorizedException('Invalid token.');
        }

        [$headerPart, $payloadPart, $signaturePart] = $parts;
        $expected = self::base64UrlEncode(
            hash_hmac('sha256', "{$headerPart}.{$payloadPart}", self::secret(), true)
        );

        if (!hash_equals($expected, $signaturePart)) {
            throw new UnauthorizedException('Invalid token signature.');
        }

        $payload = json_decode(self::base64UrlDecode($payloadPart), true);
        if (!is_array($payload)) {
            throw new UnauthorizedException('Invalid token payload.');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new UnauthorizedException('Token expired.');
        }

        return $payload;
    }

    private static function secret(): string
    {
        return getenv('APP_JWT_SECRET') ?: 'autoshare-dev-secret';
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}

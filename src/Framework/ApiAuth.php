<?php

namespace App\Framework;

use App\Exceptions\ForbiddenException;
use App\Exceptions\UnauthorizedException;
use App\Repositories\UserRepository;

class ApiAuth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        $token = Request::capture()->bearerToken();
        if ($token === null) {
            return null;
        }

        try {
            $payload = Jwt::decode($token);
            $userId = (int)($payload['sub'] ?? 0);
            if ($userId <= 0) {
                return null;
            }

            self::$user = (new UserRepository())->findPublicById($userId);
            return self::$user;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if ($user === null) {
            throw new UnauthorizedException('You need to log in first.');
        }

        return $user;
    }

    public static function requireRole(array $roles): array
    {
        $user = self::requireUser();
        if (!in_array($user['role'] ?? '', $roles, true)) {
            throw new ForbiddenException('You are not allowed to perform this action.');
        }

        return $user;
    }
}

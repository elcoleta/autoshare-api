<?php

namespace App\Framework;

class Roles
{
    public const CUSTOMER = 'customer';
    public const OWNER = 'owner';
    public const ADMIN = 'admin';

    public static function all(): array
    {
        return [self::CUSTOMER, self::OWNER, self::ADMIN];
    }
}

<?php

namespace App\Enums;

enum UserRole: string
{
    case Parent = 'parent';
    case Operative = 'operative';
    case Admin = 'admin';
    case Moderator = 'moderator';

    public static function assignable(): array
    {
        return [self::Parent, self::Operative];
    }

    public static function assignableValues(): array
    {
        return array_map(static fn (self $role) => $role->value, self::assignable());
    }
}
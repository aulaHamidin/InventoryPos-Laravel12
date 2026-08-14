<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Staff => 'Staff',
        };
    }
}

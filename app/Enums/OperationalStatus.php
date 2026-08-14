<?php

namespace App\Enums;

enum OperationalStatus: string
{
    case Active = 'active';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Banned => 'Diblokir',
        };
    }

    public function canOperate(): bool
    {
        return $this === self::Active;
    }
}

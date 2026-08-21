<?php

namespace App\Enums;

enum MovementClass: string
{
    case Unclassified = 'unclassified';
    case Fast = 'fast';
    case Normal = 'normal';
    case Slow = 'slow';
    case Dead = 'dead';

    public function label(): string
    {
        return match ($this) {
            self::Unclassified => 'Belum Terklasifikasi',
            self::Fast => 'Fast Moving',
            self::Normal => 'Normal',
            self::Slow => 'Slow Moving',
            self::Dead => 'Dead Stock',
        };
    }
}

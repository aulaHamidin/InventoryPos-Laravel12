<?php

namespace App\Enums;

enum StockOpnameStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';

    public function canTransitionTo(self $target): bool
    {
        return $this === self::Draft && $target === self::Completed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Sedang Dihitung',
            self::Completed => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Completed => 'success',
        };
    }
}

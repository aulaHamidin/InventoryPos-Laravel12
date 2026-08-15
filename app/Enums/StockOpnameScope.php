<?php

namespace App\Enums;

enum StockOpnameScope: string
{
    case Partial = 'partial';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Partial => 'Per Rak',
            self::Full => 'Semua Barang',
        };
    }
}

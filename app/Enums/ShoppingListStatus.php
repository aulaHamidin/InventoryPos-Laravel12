<?php

namespace App\Enums;

enum ShoppingListStatus: string
{
    case Draft = 'draft';
    case Purchased = 'purchased';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Purchased => 'Dibeli',
            self::Completed => 'Selesai',
            self::Archived => 'Diarsipkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Purchased => 'warning',
            self::Completed => 'success',
            self::Archived => 'info',
        };
    }
}

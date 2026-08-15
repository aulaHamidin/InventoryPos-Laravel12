<?php

namespace App\Enums;

enum PosPaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Qris => 'QRIS Statis',
            self::Transfer => 'Transfer Bank',
        };
    }

    public function isManual(): bool
    {
        return $this !== self::Cash;
    }
}

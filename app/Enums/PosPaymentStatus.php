<?php

namespace App\Enums;

enum PosPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case RefundRequired = 'refund_required';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Paid => 'Lunas',
            self::Failed => 'Gagal',
            self::RefundRequired => 'Perlu Refund',
            self::PartiallyRefunded => 'Refund Sebagian',
            self::Refunded => 'Sudah Refund',
        };
    }
}

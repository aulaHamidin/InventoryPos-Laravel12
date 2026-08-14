<?php

namespace App\Enums;

enum PosTransactionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
    case RefundRequired = 'refund_required';
    case Voided = 'voided';
    case PartiallyReturned = 'partially_returned';
    case FullyReturned = 'fully_returned';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu Pembayaran',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
            self::Expired => 'Kedaluwarsa',
            self::RefundRequired => 'Perlu Refund',
            self::Voided => 'Dibatalkan',
            self::PartiallyReturned => 'Retur Sebagian',
            self::FullyReturned => 'Retur Penuh',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Expired => 'gray',
            self::RefundRequired => 'danger',
            self::Voided => 'danger',
            self::PartiallyReturned => 'warning',
            self::FullyReturned => 'info',
        };
    }
}

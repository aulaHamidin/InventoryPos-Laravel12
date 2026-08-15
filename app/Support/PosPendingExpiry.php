<?php

namespace App\Support;

use App\Models\PosTransaction;
use Carbon\CarbonInterface;

final class PosPendingExpiry
{
    public static function hours(): int
    {
        return max(1, (int) config('pos.pending_transaction_expiry_hours', 24));
    }

    public static function cutoff(?CarbonInterface $now = null): CarbonInterface
    {
        return ($now ?? now())->copy()->subHours(self::hours());
    }

    public static function isDue(PosTransaction $transaction, ?CarbonInterface $now = null): bool
    {
        return $transaction->created_at->lessThanOrEqualTo(self::cutoff($now));
    }
}

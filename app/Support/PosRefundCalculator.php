<?php

namespace App\Support;

use App\Enums\PosTransactionStatus;
use App\Models\PosPayment;
use App\Models\PosTransaction;
use App\Models\PosTransactionItem;

final class PosRefundCalculator
{
    public static function lineTarget(PosTransactionItem $line, ?int $returnedQty = null): string
    {
        $returnedQty ??= $line->returned_qty;
        $netMinor = self::toMinor(Decimal::sub((string) $line->subtotal_amount, (string) $line->discount_amount));
        $baseMinor = intdiv($netMinor, $line->qty);
        $targetMinor = $returnedQty >= $line->qty ? $netMinor : $baseMinor * $returnedQty;

        return self::fromMinor($targetMinor);
    }

    public static function obligation(PosPayment $payment): string
    {
        $transaction = $payment->relationLoaded('transaction')
            ? $payment->transaction
            : $payment->transaction()->with('items')->firstOrFail();

        if (! $transaction->relationLoaded('items')) {
            $transaction->load('items');
        }

        if (in_array($transaction->status, [PosTransactionStatus::RefundRequired, PosTransactionStatus::Voided], true)) {
            return Decimal::money($payment->amount);
        }

        return self::returnObligation($transaction);
    }

    public static function returnObligation(PosTransaction $transaction): string
    {
        if (! $transaction->relationLoaded('items')) {
            $transaction->load('items');
        }

        return $transaction->items->reduce(
            fn (string $total, PosTransactionItem $line): string => Decimal::add($total, self::lineTarget($line)),
            '0.00',
        );
    }

    public static function due(PosPayment $payment): string
    {
        $due = Decimal::sub(self::obligation($payment), Decimal::money($payment->refunded_amount ?? 0));

        return Decimal::compare($due, '0.00') > 0 ? $due : '0.00';
    }

    private static function toMinor(string $amount): int
    {
        return (int) bcmul($amount, '100', 0);
    }

    private static function fromMinor(int $minor): string
    {
        return bcdiv((string) $minor, '100', 2);
    }
}

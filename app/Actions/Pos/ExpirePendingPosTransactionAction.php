<?php

namespace App\Actions\Pos;

use App\Actions\Audit\RecordAuditAction;
use App\Enums\PosTransactionStatus;
use App\Models\PosTransaction;
use App\Support\PosPendingExpiry;
use Illuminate\Support\Facades\DB;

class ExpirePendingPosTransactionAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function execute(int $transactionId): bool
    {
        return DB::transaction(function () use ($transactionId): bool {
            $transaction = PosTransaction::whereKey($transactionId)->lockForUpdate()->first();

            if ($transaction === null
                || $transaction->status !== PosTransactionStatus::PendingPayment
                || ! PosPendingExpiry::isDue($transaction)) {
                return false;
            }

            $transaction->update(['status' => PosTransactionStatus::Expired]);
            $this->audit->execute(
                'pos.expired',
                null,
                $transaction,
                oldValues: ['status' => PosTransactionStatus::PendingPayment->value],
                newValues: ['status' => PosTransactionStatus::Expired->value],
                metadata: ['source' => 'scheduler'],
            );

            return true;
        }, 3);
    }
}

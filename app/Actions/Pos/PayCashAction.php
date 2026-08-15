<?php

namespace App\Actions\Pos;

use App\Data\FinalizePosPaymentCommand;
use App\Enums\PosPaymentMethod;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

class PayCashAction
{
    public function __construct(private readonly FinalizePosTransactionAction $finalizer) {}

    public function execute(int $transactionId, int|float|string $cashReceived, User $actor, ?AuditContext $context = null): array
    {
        try {
            $cash = Decimal::money($cashReceived);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['cash_received' => ['Nilai uang diterima tidak valid.']]);
        }

        return $this->finalizer->execute(new FinalizePosPaymentCommand(
            transactionId: $transactionId,
            method: PosPaymentMethod::Cash,
            actor: $actor,
            cashReceived: $cash,
            auditContext: $context,
        ));
    }
}

<?php

namespace App\Actions\Pos;

use App\Data\FinalizePosPaymentCommand;
use App\Enums\PosPaymentMethod;
use App\Models\User;
use App\Support\AuditContext;
use Illuminate\Validation\ValidationException;

class ConfirmManualPaymentAction
{
    public function __construct(private readonly FinalizePosTransactionAction $finalizer) {}

    public function execute(
        int $transactionId,
        string $method,
        string $idempotencyKey,
        User $actor,
        ?string $reference = null,
        ?string $note = null,
        ?AuditContext $context = null,
    ): array {
        $paymentMethod = PosPaymentMethod::tryFrom($method);
        $reference = $this->normalize($reference);
        $note = $this->normalize($note);
        $idempotencyKey = trim($idempotencyKey);

        if ($paymentMethod === null || ! $paymentMethod->isManual()) {
            throw ValidationException::withMessages(['method' => ['Metode harus qris atau transfer.']]);
        }

        if ($idempotencyKey === '') {
            throw ValidationException::withMessages(['idempotency_key' => ['Idempotency-Key wajib diisi.']]);
        }

        if (($reference !== null && mb_strlen($reference) > 255) || ($note !== null && mb_strlen($note) > 1000)) {
            throw ValidationException::withMessages([
                'reference' => ['Reference maksimal 255 karakter.'],
                'note' => ['Catatan maksimal 1000 karakter.'],
            ]);
        }

        return $this->finalizer->execute(new FinalizePosPaymentCommand(
            transactionId: $transactionId,
            method: $paymentMethod,
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            manualReference: $reference,
            confirmationNote: $note,
            auditContext: $context,
        ));
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

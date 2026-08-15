<?php

namespace App\Data;

use App\Enums\PosPaymentMethod;
use App\Models\User;
use App\Support\AuditContext;

final readonly class FinalizePosPaymentCommand
{
    public function __construct(
        public int $transactionId,
        public PosPaymentMethod $method,
        public User $actor,
        public ?string $idempotencyKey = null,
        public ?string $cashReceived = null,
        public ?string $manualReference = null,
        public ?string $confirmationNote = null,
        public ?AuditContext $auditContext = null,
    ) {}
}

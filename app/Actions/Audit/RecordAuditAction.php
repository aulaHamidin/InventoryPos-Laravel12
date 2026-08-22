<?php

namespace App\Actions\Audit;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use App\Support\SensitiveDataRedactor;
use Illuminate\Database\Eloquent\Model;

class RecordAuditAction
{
    public function __construct(private readonly SensitiveDataRedactor $redactor) {}

    public function execute(
        string $action,
        User|Admin|null $actor = null,
        Model|string|null $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?AuditContext $context = null,
        ?array $metadata = null,
        ?int $tenantId = null,
        bool $global = false,
    ): AuditLog {
        $log = new AuditLog([
            'actor_type' => match (true) {
                $actor instanceof Admin => 'admin',
                $actor instanceof User => 'user',
                default => 'system',
            },
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject instanceof Model ? $subject::class : $subject,
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'old_values' => $this->redactor->redact($oldValues),
            'new_values' => $this->redactor->redact($newValues),
            'ip_address' => $context?->ipAddress,
            'user_agent' => $context?->userAgent !== null
                ? $this->redactor->redactText($context->userAgent)
                : null,
            'metadata' => $this->redactor->redact(array_merge($context?->metadata ?? [], $metadata ?? [])),
        ]);
        $log->tenant_id = $global ? null : ($tenantId
            ?? (TenantContext::hasTenant() ? TenantContext::id() : ($actor instanceof User ? $actor->tenant_id : null)));
        $log->save();

        return $log;
    }
}

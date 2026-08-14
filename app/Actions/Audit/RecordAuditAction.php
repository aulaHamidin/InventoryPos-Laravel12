<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;

class RecordAuditAction
{
    public function execute(
        string $action,
        ?User $actor = null,
        Model|string|null $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?AuditContext $context = null,
        ?array $metadata = null,
    ): AuditLog {
        $log = new AuditLog([
            'actor_type' => $actor ? 'user' : 'system',
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject instanceof Model ? $subject::class : $subject,
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $context?->ipAddress,
            'user_agent' => $context?->userAgent,
            'metadata' => array_merge($context?->metadata ?? [], $metadata ?? []),
        ]);
        $log->tenant_id = TenantContext::hasTenant() ? TenantContext::id() : $actor?->tenant_id;
        $log->save();

        return $log;
    }
}

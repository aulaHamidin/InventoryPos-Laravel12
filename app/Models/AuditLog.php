<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AuditLog extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_type', 'actor_id', 'action', 'subject_type', 'subject_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'metadata',
    ];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('Audit logs are immutable and cannot be updated.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('Audit logs are immutable and cannot be deleted.');
    }
}

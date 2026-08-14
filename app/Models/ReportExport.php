<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Concerns\PreventsHistoricalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use HasTenantScope, PreventsHistoricalDeletion;

    protected $fillable = [
        'user_id', 'report_type', 'format', 'status', 'progress', 'filters',
        'path', 'file_name', 'error', 'completed_at',
    ];

    protected $hidden = ['path', 'error'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'progress' => 'integer', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

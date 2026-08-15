<?php

namespace App\Models;

use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class StockOpname extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $fillable = ['created_by', 'scope_type', 'rack_id', 'status', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'scope_type' => StockOpnameScope::class,
            'status' => StockOpnameStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $opname): void {
            if ($opname->getRawOriginal('status') === StockOpnameStatus::Completed->value) {
                throw new LogicException('Completed stock opname cannot be edited.');
            }
        });

        static::deleting(function (self $opname): void {
            if ($opname->status === StockOpnameStatus::Completed) {
                throw new LogicException('Completed stock opname cannot be deleted.');
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(StockOpnameDetail::class);
    }
}

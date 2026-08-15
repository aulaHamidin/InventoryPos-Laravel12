<?php

namespace App\Models;

use App\Enums\StockOpnameStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockOpnameDetail extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'stock_opname_id', 'item_id', 'qty_sistem_at_count', 'qty_fisik', 'counted_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'qty_sistem_at_count' => 'integer',
            'qty_fisik' => 'integer',
            'counted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $detail): void {
            $status = $detail->opname()->withoutGlobalScopes()->value('status');
            $status = $status instanceof StockOpnameStatus ? $status : StockOpnameStatus::tryFrom((string) $status);
            if ($status === StockOpnameStatus::Completed) {
                throw new LogicException('Completed stock opname details cannot be changed.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }
}

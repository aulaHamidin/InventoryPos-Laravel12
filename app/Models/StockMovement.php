<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class StockMovement extends Model
{
    use HasTenantScope;

    protected $table = 'item_stock_movements';

    public const UPDATED_AT = null;

    protected $fillable = [
        'item_id', 'user_id', 'supplier_id', 'movement_type', 'qty', 'direction',
        'harga_satuan', 'note', 'reference_type', 'reference_id',
    ];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'harga_satuan' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('Stock movements are immutable and cannot be updated.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('Stock movements are immutable and cannot be deleted.');
    }
}

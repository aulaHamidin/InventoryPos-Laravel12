<?php

namespace App\Models;

use App\Enums\ShoppingListStatus;
use App\Models\Concerns\HasTenantScope;
use App\Models\Concerns\PreventsHistoricalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingList extends Model
{
    use HasTenantScope, PreventsHistoricalDeletion;

    protected $fillable = ['created_by', 'status', 'submitted_at', 'completed_at'];

    protected function casts(): array
    {
        return ['status' => ShoppingListStatus::class, 'submitted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class);
    }
}

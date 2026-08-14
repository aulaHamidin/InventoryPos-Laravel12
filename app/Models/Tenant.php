<?php

namespace App\Models;

use App\Enums\OperationalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['nama_toko', 'slug', 'operational_status', 'allow_negative_stock', 'dead_stock_days'];

    protected function casts(): array
    {
        return [
            'operational_status' => OperationalStatus::class,
            'allow_negative_stock' => 'boolean',
            'dead_stock_days' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function canOperate(): bool
    {
        return $this->operational_status->canOperate();
    }
}

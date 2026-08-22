<?php

namespace App\Models;

use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

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

    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->withoutGlobalScopes()->where('role', UserRole::Owner);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->whereNot('status', 'expired')->latestOfMany();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(TenantDeletionRequest::class);
    }

    public function canOperate(): bool
    {
        return $this->operational_status->canOperate();
    }
}

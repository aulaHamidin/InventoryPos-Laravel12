<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Plan extends Model
{
    protected static function booted(): void
    {
        static::updating(function (Plan $plan): void {
            $immutable = ['code', 'name', 'billing_interval', 'price', 'is_trial', 'trial_days'];
            if ($plan->isDirty($immutable) && $plan->hasReferences()) {
                throw new LogicException('Referenced plans are immutable; clone a new version.');
            }
        });
    }

    protected $fillable = [
        'code', 'name', 'billing_interval', 'price', 'is_trial', 'trial_days',
        'is_active', 'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'billing_interval' => BillingInterval::class,
            'price' => 'decimal:2',
            'is_trial' => 'boolean',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'is_internal' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasReferences(): bool
    {
        return $this->subscriptions()->exists() || Invoice::query()->where('target_plan_id', $this->getKey())->exists();
    }
}
